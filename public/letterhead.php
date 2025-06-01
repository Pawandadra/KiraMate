<?php
require_once __DIR__ . "/../config/database.php";

try {
    $db = Database::getInstance();
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // Fallback to default values if database error occurs
    $settings = [
        'company_name' => 'Company Name',
        'company_address' => 'Company Address',
        'company_logo' => 'default_logo.png'
    ];
}

// Ensure we have all required settings
$company_name = $settings['company_name'] ?? 'Company Name';
$company_address = $settings['company_address'] ?? 'Company Address';
$company_logo = $settings['company_logo'] ?? 'default_logo.png';

// Determine logo path
$logo_path = $company_logo === 'default_logo.png' 
    ? BASE_PATH . '/assets/images/default_logo.png'
    : BASE_PATH . '/uploads/' . $company_logo;
?>
<div class="letterhead">
    <div class="letterhead-content">
        <div class="logo-container">
            <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                 alt="Company Logo" class="company-logo">
        </div>
        <div class="company-details">
            <h1 class="company-name"><?php echo htmlspecialchars($company_name); ?></h1>
            <p class="company-address"><?php echo nl2br(htmlspecialchars($company_address)); ?></p>
        </div>
    </div>
</div>
<style>
.letterhead {
    border-bottom: 2px solid #222;
    padding-bottom: 10px;
    margin-bottom: 30px;
}

.letterhead-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logo-container {
    flex-shrink: 0;
}

.company-logo {
    height: 80px;
    width: auto;
    object-fit: contain;
}

.company-details {
    flex-grow: 1;
}

.company-name {
    margin: 0;
    font-size: 1.7em;
    font-weight: bold;
    letter-spacing: 1px;
}

.company-address {
    margin: 5px 0 0 0;
    font-size: 1.1em;
    line-height: 1.4;
}
</style>