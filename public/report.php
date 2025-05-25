<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Security.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Initialize security
Security::init();
Security::requireLogin();

// Rate limiting using session
$rate_limit_key = 'report_' . $_SERVER['REMOTE_ADDR'];
$rate_limit_count = $_SESSION[$rate_limit_key] ?? 0;
$rate_limit_time = $_SESSION[$rate_limit_key . '_time'] ?? 0;

// Reset counter if more than 60 seconds have passed
if (time() - $rate_limit_time > 60) {
    $rate_limit_count = 0;
    $rate_limit_time = time();
}

if ($rate_limit_count > 20) { // Max 20 requests per minute
    http_response_code(429);
    die('Too many requests. Please try again later.');
}

// Update rate limit counter
$_SESSION[$rate_limit_key] = $rate_limit_count + 1;
$_SESSION[$rate_limit_key . '_time'] = $rate_limit_time;

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

// Input validation
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateShopNo($shop_no) {
    return preg_match('/^[A-Za-z0-9-]+$/', $shop_no);
}

function validateTenantName($name) {
    return preg_match('/^[A-Za-z\s]+$/', $name);
}

// Validate inputs
$report_type = $_GET['report_type'] ?? 'payments';
if (!in_array($report_type, ['payments', 'rents', 'tenants', 'shops', 'opening_balances', 'summary'])) {
    $report_type = 'payments';
}

$from_date = $_GET['from_date'] ?? '';
if ($from_date && !validateDate($from_date)) {
    $from_date = '';
}

$to_date = $_GET['to_date'] ?? '';
if ($to_date && !validateDate($to_date)) {
    $to_date = '';
}

$shop_no = $_GET['shop_no'] ?? '';
if ($shop_no && !validateShopNo($shop_no)) {
    $shop_no = '';
}

$tenant_name = $_GET['tenant_name'] ?? '';
if ($tenant_name && !validateTenantName($tenant_name)) {
    $tenant_name = '';
}

$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'paid', 'pending'])) {
    $status = '';
}

$sort_by = $_GET['sort_by'] ?? '';
$sort_order = strtolower($_GET['sort_order'] ?? '');
if ($sort_order !== 'desc') $sort_order = 'asc';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    $db = Database::getInstance();
    $data = [];
    $columns = [];
    $title = '';
    $total_records = 0;

    switch ($report_type) {
        case 'payments':
            $title = 'Payments Report';
            $query = "SELECT p.*, s.shop_no, t.name as tenant_name FROM payments p JOIN shops s ON p.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id WHERE 1=1";
            $count_query = "SELECT COUNT(*) as total FROM payments p JOIN shops s ON p.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id WHERE 1=1";
            $params = [];
            if ($shop_no) { 
                $query .= " AND s.shop_no = ?"; 
                $count_query .= " AND s.shop_no = ?";
                $params[] = $shop_no; 
            }
            if ($from_date) { 
                $query .= " AND p.payment_date >= ?"; 
                $count_query .= " AND p.payment_date >= ?";
                $params[] = $from_date; 
            }
            if ($to_date) { 
                $query .= " AND p.payment_date <= ?"; 
                $count_query .= " AND p.payment_date <= ?";
                $params[] = $to_date; 
            }
            $valid_cols = ['shop_no','tenant_name','rent_year','rent_month','amount','payment_method','payment_date'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                $query .= " ORDER BY $sort_by $sort_order";
            } else {
                $query .= " ORDER BY p.payment_date DESC";
            }
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $per_page;
            $params[] = $offset;
            
            $total_records = $db->query($count_query, array_slice($params, 0, -2))->fetch()['total'];
            $data = $db->query($query, $params)->fetchAll();
            $columns = ['Shop No', 'Tenant', 'Rent Month', 'Amount', 'Payment Method', 'Payment Date'];
            break;
        case 'rents':
            $title = 'Rents Report';
            $query = "SELECT r.*, s.shop_no, t.name as tenant_name FROM rents r JOIN shops s ON r.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id WHERE 1=1";
            $params = [];
            if ($shop_no) { $query .= " AND s.shop_no = ?"; $params[] = $shop_no; }
            if ($from_date) { $query .= " AND r.created_at >= ?"; $params[] = $from_date; }
            if ($to_date) { $query .= " AND r.created_at <= ?"; $params[] = $to_date; }
            if ($status === 'paid') {
                $query .= " AND EXISTS (SELECT 1 FROM payments p WHERE p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month)";
            } elseif ($status === 'pending') {
                $query .= " AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month)";
            }
            $valid_cols = ['shop_no','tenant_name','rent_year','rent_month','calculated_rent','penalty','amount_waved_off','final_rent'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                $query .= " ORDER BY $sort_by $sort_order";
            } else {
                $query .= " ORDER BY r.rent_year DESC, r.rent_month DESC";
            }
            $data = $db->query($query, $params)->fetchAll();
            $columns = ['Shop No', 'Tenant', 'Rent Month', 'Base Rent', 'Penalty', 'Waved Off', 'Final Amount'];
            break;
        case 'tenants':
            $title = 'Tenants Report';
            $query = "SELECT * FROM tenants WHERE 1=1";
            $params = [];
            if ($tenant_name) { $query .= " AND name LIKE ?"; $params[] = "%$tenant_name%"; }
            if ($from_date) { $query .= " AND created_at >= ?"; $params[] = $from_date; }
            if ($to_date) { $query .= " AND created_at <= ?"; $params[] = $to_date; }
            $valid_cols = ['tenant_id','name','mobile','email','aadhaar_number','pancard_number'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                $query .= " ORDER BY $sort_by $sort_order";
            } else {
                $query .= " ORDER BY name";
            }
            $data = $db->query($query, $params)->fetchAll();
            $columns = ['Tenant ID', 'Name', 'Mobile', 'Email', 'Aadhaar', 'PAN', 'Address'];
            break;
        case 'shops':
            $title = 'Shops Report';
            $query = "SELECT s.*, t.name as tenant_name FROM shops s LEFT JOIN tenants t ON s.tenant_id = t.id WHERE 1=1";
            $params = [];
            if ($shop_no) { $query .= " AND s.shop_no = ?"; $params[] = $shop_no; }
            $valid_cols = ['shop_no','location','tenant_name','agreement_start_date','agreement_end_date','base_rent','rent_increment_percent','increment_duration_years'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                $query .= " ORDER BY $sort_by $sort_order";
            } else {
                $query .= " ORDER BY s.shop_no";
            }
            $data = $db->query($query, $params)->fetchAll();
            $columns = ['Shop No', 'Location', 'Tenant', 'Agreement Start', 'Agreement End', 'Base Rent', 'Rent Increment', 'Increment Duration'];
            break;
        case 'opening_balances':
            $title = 'Opening Balances Report';
            $query = "SELECT ob.*, s.shop_no, t.name as tenant_name FROM opening_balances ob JOIN shops s ON ob.shop_id = s.id LEFT JOIN tenants t ON ob.tenant_id = t.id WHERE 1=1";
            $params = [];
            if ($shop_no) { $query .= " AND s.shop_no = ?"; $params[] = $shop_no; }
            if ($from_date) { $query .= " AND ob.created_at >= ?"; $params[] = $from_date; }
            if ($to_date) { $query .= " AND ob.created_at <= ?"; $params[] = $to_date; }
            if ($status === 'paid') {
                $query .= " AND EXISTS (SELECT 1 FROM payments p WHERE p.shop_id = ob.shop_id AND p.ob_financial_year = ob.financial_year)";
            } elseif ($status === 'pending') {
                $query .= " AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.shop_id = ob.shop_id AND p.ob_financial_year = ob.financial_year)";
            }
            $valid_cols = ['shop_no','tenant_name','opening_balance','financial_year'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                $query .= " ORDER BY $sort_by $sort_order";
            } else {
                $query .= " ORDER BY ob.financial_year DESC, s.shop_no ASC";
            }
            $data = $db->query($query, $params)->fetchAll();
            $columns = ['Shop No', 'Tenant', 'Opening Balance', 'Financial Year'];
            break;
        case 'summary':
            $title = 'Summary Report';
            $columns = ['Shop No', 'Tenant', 'Opening Balance', 'Remaining Amount', 'Paid Amount'];
            $query = "SELECT s.id, s.shop_no, t.name as tenant_name FROM shops s LEFT JOIN tenants t ON s.tenant_id = t.id WHERE 1=1";
            $params = [];
            if ($shop_no) { $query .= " AND s.shop_no = ?"; $params[] = $shop_no; }
            $query .= " ORDER BY s.shop_no";
            $shops = $db->query($query, $params)->fetchAll();
            $data = [];
            foreach ($shops as $shop) {
                $shop_id = $shop['id'];
                // Opening balance (sum of all opening balances for this shop)
                $ob = $db->query("SELECT SUM(opening_balance) as ob_sum FROM opening_balances WHERE shop_id = ?" .
                    ($from_date ? " AND created_at >= '$from_date'" : '') .
                    ($to_date ? " AND created_at <= '$to_date'" : ''), [$shop_id])->fetch();
                $opening_balance = $ob ? (float)$ob['ob_sum'] : 0.0;
                // Paid amount (sum of all payments for this shop)
                $paid = $db->query("SELECT SUM(amount) as paid_sum FROM payments WHERE shop_id = ?" .
                    ($from_date ? " AND payment_date >= '$from_date'" : '') .
                    ($to_date ? " AND payment_date <= '$to_date'" : ''), [$shop_id])->fetch();
                $paid_amount = $paid ? (float)$paid['paid_sum'] : 0.0;
                // Remaining unpaid rents
                $remaining = $db->query(
                    "SELECT SUM(r.final_rent) as remain_sum
                     FROM rents r
                     WHERE r.shop_id = ?
                     AND NOT EXISTS (
                         SELECT 1 FROM payments p WHERE p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month
                     )" .
                    ($from_date ? " AND r.created_at >= '$from_date'" : '') .
                    ($to_date ? " AND r.created_at <= '$to_date'" : ''),
                    [$shop_id]
                )->fetch();
                $remaining_amount = $remaining ? (float)$remaining['remain_sum'] : 0.0;
                // Add unpaid opening balances
                $unpaid_ob = $db->query(
                    "SELECT SUM(ob.opening_balance) as unpaid_ob_sum
                     FROM opening_balances ob
                     WHERE ob.shop_id = ?
                     AND NOT EXISTS (
                         SELECT 1 FROM payments p WHERE p.shop_id = ob.shop_id AND p.ob_financial_year = ob.financial_year
                     )" .
                    ($from_date ? " AND ob.created_at >= '$from_date'" : '') .
                    ($to_date ? " AND ob.created_at <= '$to_date'" : ''),
                    [$shop_id]
                )->fetch();
                $remaining_amount += $unpaid_ob && $unpaid_ob['unpaid_ob_sum'] ? (float)$unpaid_ob['unpaid_ob_sum'] : 0.0;
                $data[] = [
                    'shop_no' => $shop['shop_no'],
                    'tenant_name' => $shop['tenant_name'],
                    'opening_balance' => $opening_balance,
                    'remaining_amount' => $remaining_amount,
                    'paid_amount' => $paid_amount
                ];
            }
            // PHP sort for summary
            $valid_cols = ['shop_no','tenant_name','opening_balance','remaining_amount','paid_amount'];
            if ($sort_by && in_array($sort_by, $valid_cols)) {
                usort($data, function($a, $b) use ($sort_by, $sort_order) {
                    if ($a[$sort_by] == $b[$sort_by]) return 0;
                    if ($sort_order === 'desc') {
                        return ($a[$sort_by] < $b[$sort_by]) ? 1 : -1;
                    } else {
                        return ($a[$sort_by] > $b[$sort_by]) ? 1 : -1;
                    }
                });
            }
            break;
        case '':
            $title = '';
            $columns = [];
            $data = [];
            break;
        default:
            $title = 'Report';
            $columns = [];
            $data = [];
    }

    // Cache the results for 5 minutes
    $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
    $_SESSION[$rate_limit_key . '_time'] = time();

} catch (Exception $e) {
    Logger::error('Report generation failed: ' . $e->getMessage());
    $error = 'An error occurred while generating the report. Please try again later.';
    $data = [];
    $columns = [];
    $title = 'Error';
}

function safe($v) { return htmlspecialchars($v ?? '-'); }

function render_row($report_type, $row) {
    switch ($report_type) {
        case 'payments':
            return [
                ['value' => safe($row['shop_no']), 'type' => 'text'],
                ['value' => safe($row['tenant_name']), 'type' => 'text'],
                ['value' => !empty($row['ob_financial_year']) ? 'Opening Balance (FY ' . safe($row['ob_financial_year']) . ')' : date('F Y', strtotime($row['rent_year'] . '-' . $row['rent_month'] . '-01')), 'type' => 'date'],
                ['value' => number_format($row['amount'], 2), 'type' => 'number'],
                ['value' => safe($row['payment_method']), 'type' => 'text'],
                ['value' => date('d M Y', strtotime($row['payment_date'])), 'type' => 'date'],
            ];
        case 'rents':
            return [
                ['value' => safe($row['shop_no']), 'type' => 'text'],
                ['value' => safe($row['tenant_name']), 'type' => 'text'],
                ['value' => date('F Y', strtotime($row['rent_year'] . '-' . $row['rent_month'] . '-01')), 'type' => 'date'],
                ['value' => number_format(isset($row['calculated_rent']) && $row['calculated_rent'] !== null ? $row['calculated_rent'] : 0, 2), 'type' => 'number'],
                ['value' => number_format(isset($row['penalty']) && $row['penalty'] !== null ? $row['penalty'] : 0, 2), 'type' => 'number'],
                ['value' => number_format(isset($row['amount_waved_off']) && $row['amount_waved_off'] !== null ? $row['amount_waved_off'] : 0, 2), 'type' => 'number'],
                ['value' => number_format(isset($row['final_rent']) && $row['final_rent'] !== null ? $row['final_rent'] : 0, 2), 'type' => 'number'],
            ];
        case 'tenants':
            return [
                ['value' => safe($row['tenant_id']), 'type' => 'text'],
                ['value' => safe($row['name']), 'type' => 'text'],
                ['value' => safe($row['mobile']), 'type' => 'text'],
                ['value' => safe($row['email']), 'type' => 'text'],
                ['value' => safe($row['aadhaar_number']), 'type' => 'text'],
                ['value' => safe($row['pancard_number']), 'type' => 'text'],
                ['value' => nl2br(safe($row['address'])), 'type' => 'text'],
            ];
        case 'shops':
            return [
                ['value' => safe($row['shop_no']), 'type' => 'text'],
                ['value' => safe($row['location']), 'type' => 'text'],
                ['value' => safe($row['tenant_name']), 'type' => 'text'],
                ['value' => date('d M Y', strtotime($row['agreement_start_date'])), 'type' => 'date'],
                ['value' => date('d M Y', strtotime($row['agreement_end_date'])), 'type' => 'date'],
                ['value' => number_format($row['base_rent'], 2), 'type' => 'number'],
                ['value' => number_format(isset($row['rent_increment_percent']) && $row['rent_increment_percent'] !== null ? $row['rent_increment_percent'] : 0, 2), 'type' => 'number'],
                ['value' => isset($row['increment_duration_years']) && $row['increment_duration_years'] !== null ? safe($row['increment_duration_years']) : 0, 'type' => 'number'],
            ];
        case 'opening_balances':
            return [
                ['value' => safe($row['shop_no']), 'type' => 'text'],
                ['value' => safe($row['tenant_name']), 'type' => 'text'],
                ['value' => number_format($row['opening_balance'], 2), 'type' => 'number'],
                ['value' => safe($row['financial_year']), 'type' => 'text'],
            ];
        case 'summary':
            return [
                ['value' => safe($row['shop_no']), 'type' => 'text'],
                ['value' => safe($row['tenant_name']), 'type' => 'text'],
                ['value' => number_format($row['opening_balance'], 2), 'type' => 'number'],
                ['value' => number_format($row['remaining_amount'], 2), 'type' => 'number'],
                ['value' => number_format($row['paid_amount'], 2), 'type' => 'number'],
            ];
        default:
            return [];
    }
}

// Build dynamic report heading
function build_report_heading($report_type, $from_date, $to_date, $shop_no, $tenant_name, $status) {
    $type_map = [
        'payments' => 'Payments',
        'rents' => 'Rents',
        'tenants' => 'Tenants',
        'shops' => 'Shops',
        'opening_balances' => 'Opening Balances',
        'summary' => 'Summary',
    ];
    $type = $type_map[$report_type] ?? ucfirst($report_type);
    $parts = [];
    // Shop No
    if ($shop_no) {
        $parts[] = "for Shop No $shop_no";
    }
    // Tenant
    if ($tenant_name) {
        $parts[] = "for Tenant $tenant_name";
    }
    // Date range
    if ($from_date && $to_date) {
        $parts[] = "(From $from_date To $to_date)";
    } elseif ($from_date) {
        $parts[] = "(From $from_date)";
    } elseif ($to_date) {
        $parts[] = "(To $to_date)";
    }
    // Paid/Pending
    if ($status === 'paid') {
        $parts[] = "(Payment Done)";
    } elseif ($status === 'pending') {
        $parts[] = "(Payment Pending)";
    }
    $middle = '';
    if (!empty($parts)) {
        $middle = ' ' . implode(' ', $parts);
    }
    return "$type Report$middle";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo safe($title); ?></title>
    <style>
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .a4-container { box-shadow: none !important; margin: 0 !important; }
        }
        body {
            background: #eee;
            font-family: 'Segoe UI', 'Arial', sans-serif;
        }
        .a4-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 0 5px #bbb;
            padding: 32px 32px 16px 32px;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .letterhead {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .letterhead img {
            height: 80px;
            margin-right: 20px;
        }
        .letterhead .trust-info {
            flex: 1;
        }
        .letterhead .trust-info h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .letterhead .trust-info p {
            margin: 0;
            font-size: 1.1rem;
        }
        .report-title {
            text-align: center;
            font-size: 1.3rem;
            font-weight: bold;
            margin: 32px 0 16px 0;
            letter-spacing: 1px;
        }
        .report-meta {
            text-align: right;
            font-size: 1rem;
            margin-bottom: 16px;
        }
        .report-table {
            width: 100%;
            margin: 0 auto 32px auto;
            font-size: 1.08rem;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .report-table td, .report-table th {
            padding: 8px 12px;
            border: 1px solid #ccc;
            word-break: normal;
            word-wrap: break-word;
            white-space: normal;
            vertical-align: top;
            overflow-wrap: break-word;
            hyphens: none;
        }
        /* Column width adjustments based on content type */
        .report-table th:nth-child(1), /* Shop No */
        .report-table td:nth-child(1) {
            width: 10%;
            min-width: 100px;
        }
        .report-table th:nth-child(2), /* Tenant Name */
        .report-table td:nth-child(2) {
            width: 20%;
            min-width: 150px;
        }
        .report-table th:nth-child(3), /* Dates/Month */
        .report-table td:nth-child(3) {
            width: 12%;
            min-width: 120px;
        }
        .report-table th:nth-child(4), /* Amounts */
        .report-table td:nth-child(4) {
            width: 12%;
            min-width: 120px;
            text-align: right;
        }
        .report-table th:nth-child(5), /* Additional Amounts */
        .report-table td:nth-child(5) {
            width: 12%;
            min-width: 120px;
            text-align: right;
        }
        .report-table th:nth-child(6), /* Additional Amounts */
        .report-table td:nth-child(6) {
            width: 12%;
            min-width: 120px;
            text-align: right;
        }
        .report-table th:nth-child(7), /* Additional Amounts */
        .report-table td:nth-child(7) {
            width: 12%;
            min-width: 120px;
            text-align: right;
        }
        .report-table th:nth-child(8), /* Additional Amounts */
        .report-table td:nth-child(8) {
            width: 10%;
            min-width: 100px;
            text-align: right;
        }
        .report-table th {
            text-align: left;
            font-weight: 500;
            color: #222;
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        /* Right align numeric columns */
        .report-table td[data-type="number"] {
            text-align: right;
        }
        /* Center align date columns */
        .report-table td[data-type="date"] {
            text-align: center;
        }
        .print-btn {
            position: absolute;
            top: 24px;
            right: 32px;
            background: #198754;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 2px 6px #0001;
            transition: background 0.2s;
        }
        .print-btn:hover {
            background: #157347;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination-controls {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .pagination .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            color: #fff;
            background: #198754;
            border: none;
            cursor: pointer;
        }
        .pagination .btn:hover {
            background: #157347;
        }
        .page-info {
            font-size: 1rem;
            color: #666;
        }
    </style>
</head>
<body>
<div class="a4-container">
    <button class="print-btn no-print" onclick="window.print()">Print</button>
    <?php include __DIR__ . '/letterhead.php'; ?>
    <div class="report-title"><?php echo safe(build_report_heading($report_type, $from_date, $to_date, $shop_no, $tenant_name, $status)); ?></div>
    <div class="report-meta">Generated on: <?php echo date('d-m-Y'); ?></div>
    <table class="report-table">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?php echo safe($col); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="<?php echo count($columns); ?>" class="text-center">No data found.</td></tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach (render_row($report_type, $row) as $cell): ?>
                            <td data-type="<?php echo $cell['type']; ?>"><?php echo $cell['value']; ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="pagination no-print">
        <?php if ($total_records > $per_page): ?>
            <div class="pagination-controls">
                <?php
                $total_pages = ceil($total_records / $per_page);
                $current_page = $page;
                $query_params = $_GET;
                ?>
                <?php if ($current_page > 1): ?>
                    <?php $query_params['page'] = $current_page - 1; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="btn btn-secondary">Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                
                <?php if ($current_page < $total_pages): ?>
                    <?php $query_params['page'] = $current_page + 1; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="btn btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html> 