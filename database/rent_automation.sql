-- Drop existing objects if they exist
DROP EVENT IF EXISTS monthly_rent_creation;
DROP PROCEDURE IF EXISTS create_monthly_rents;

DELIMITER //

CREATE PROCEDURE create_monthly_rents()
BEGIN
    INSERT INTO rents (
        shop_id, rent_year, rent_month, calculated_rent, final_rent, penalty, amount_waved_off
    )
    SELECT
        s.id,
        YEAR(CURRENT_DATE()),
        MONTH(CURRENT_DATE()),
        CASE
            WHEN (TIMESTAMPDIFF(MONTH, s.agreement_start_date, CURRENT_DATE()) > 0)
            THEN s.base_rent + (s.base_rent * (s.rent_increment_percent / 100) *
                FLOOR(TIMESTAMPDIFF(MONTH, s.agreement_start_date, CURRENT_DATE()) / (s.increment_duration_years * 12)))
            ELSE s.base_rent
        END AS calculated_rent,
        CASE
            WHEN (TIMESTAMPDIFF(MONTH, s.agreement_start_date, CURRENT_DATE()) > 0)
            THEN s.base_rent + (s.base_rent * (s.rent_increment_percent / 100) *
                FLOOR(TIMESTAMPDIFF(MONTH, s.agreement_start_date, CURRENT_DATE()) / (s.increment_duration_years * 12)))
            ELSE s.base_rent
        END AS final_rent,
        0.00,
        0.00
    FROM shops s
    WHERE s.agreement_end_date >= CURRENT_DATE()
    AND NOT EXISTS (
        SELECT 1 FROM rents r
        WHERE r.shop_id = s.id
          AND r.rent_year = YEAR(CURRENT_DATE())
          AND r.rent_month = MONTH(CURRENT_DATE())
    );
END //

DELIMITER ;