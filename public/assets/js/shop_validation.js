// shop_validation.js - shared validation for shop create/edit
// Expects: SHOP_EDIT_ID (null for create, shop id for edit)

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (!form) return;
    const shopNoInput = document.getElementById('shop_no');
    const shopEditId = (typeof SHOP_EDIT_ID !== 'undefined') ? SHOP_EDIT_ID : null;

    async function checkShopNoUnique(value) {
        if (!value) return true;
        let url = `check_shop_number.php?shop_no=${encodeURIComponent(value)}`;
        if (shopEditId) url += `&exclude_id=${shopEditId}`;
        try {
            const response = await fetch(url);
            const data = await response.json();
            return !data.exists;
        } catch (error) {
            return false;
        }
    }

    function showValidationMessage(input, message, isValid) {
        let feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('form-text')) {
            feedback.textContent = isValid ? '' : message;
            feedback.className = `form-text${isValid ? '' : ' text-danger'}`;
        }
        input.classList.toggle('is-invalid', !isValid);
        input.classList.toggle('is-valid', isValid);
    }

    if (shopNoInput) shopNoInput.addEventListener('input', async function() {
        if (this.value.length > 0) {
            const isUnique = await checkShopNoUnique(this.value.trim());
            showValidationMessage(this, isUnique ? '' : 'This shop number already exists', isUnique);
        } else {
            showValidationMessage(this, 'Shop number is required', false);
        }
    });

    if (form) form.addEventListener('submit', async function(e) {
        if (shopNoInput && !shopNoInput.value) {
            showValidationMessage(shopNoInput, 'Shop number is required', false);
            e.preventDefault();
            return;
        }
        if (shopNoInput && shopNoInput.value) {
            const isUnique = await checkShopNoUnique(shopNoInput.value.trim());
            if (!isUnique) {
                showValidationMessage(shopNoInput, 'This shop number already exists', false);
                e.preventDefault();
                return;
            }
        }
    });
}); 