// tenant_validation.js - shared validation for tenant create/edit
// Expects: TENANT_EDIT_ID (null for create, tenant id for edit)

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (!form) return;
    const tenantIdInput = document.getElementById('tenant_id');
    const aadhaarInput = document.getElementById('aadhaar_number');
    const mobileInput = document.getElementById('mobile');
    const pancardInput = document.getElementById('pancard_number');
    const emailInput = document.getElementById('email');
    const nameInput = document.getElementById('name');
    const tenantEditId = (typeof TENANT_EDIT_ID !== 'undefined') ? TENANT_EDIT_ID : null;

    const validationState = {
        tenant_id: true,
        aadhaar_number: true,
        mobile: true,
        pancard_number: true,
        email: true
    };

    async function checkUnique(field, value) {
        if (!value) return true;
        let url = `check_unique.php?field=${field}&value=${encodeURIComponent(value)}`;
        if (tenantEditId) url += `&exclude_id=${tenantEditId}`;
        try {
            const response = await fetch(url);
            const data = await response.json();
            validationState[field] = data.unique;
            return data.unique;
        } catch (error) {
            validationState[field] = false;
            return false;
        }
    }

    function showValidationMessage(input, message, isValid) {
        // Always use the next .form-text after the input
        let feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('form-text')) {
            feedback.textContent = isValid ? '' : message;
            feedback.className = `form-text${isValid ? '' : ' text-danger'}`;
        }
        input.classList.toggle('is-invalid', !isValid);
        input.classList.toggle('is-valid', isValid);
    }

    if (aadhaarInput) aadhaarInput.addEventListener('input', async function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 12) {
            const isUnique = await checkUnique('aadhaar_number', this.value);
            showValidationMessage(this, isUnique ? '' : 'This Aadhaar number is already registered', isUnique);
        } else if (this.value.length > 0) {
            showValidationMessage(this, 'Aadhaar number must be 12 digits', false);
            validationState.aadhaar_number = false;
        } else {
            showValidationMessage(this, '', true);
            validationState.aadhaar_number = true;
        }
    });

    if (mobileInput) mobileInput.addEventListener('input', async function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 10) {
            const isUnique = await checkUnique('mobile', this.value);
            showValidationMessage(this, isUnique ? '' : 'This mobile number is already registered', isUnique);
        } else if (this.value.length > 0) {
            showValidationMessage(this, 'Mobile number must be 10 digits', false);
            validationState.mobile = false;
        } else {
            showValidationMessage(this, '', true);
            validationState.mobile = true;
        }
    });

    if (pancardInput) pancardInput.addEventListener('input', async function() {
        this.value = this.value.toUpperCase();
        if (this.value.length === 10) {
            const isUnique = await checkUnique('pancard_number', this.value);
            showValidationMessage(this, isUnique ? '' : 'This PAN card number is already registered', isUnique);
        } else if (this.value.length > 0) {
            showValidationMessage(this, 'PAN card number must be 10 characters', false);
            validationState.pancard_number = false;
        } else {
            showValidationMessage(this, '', true);
            validationState.pancard_number = true;
        }
    });

    if (tenantIdInput) tenantIdInput.addEventListener('input', async function() {
        if (this.value.length > 0) {
            const isUnique = await checkUnique('tenant_id', this.value);
            showValidationMessage(this, isUnique ? '' : 'This Tenant ID is already in use', isUnique);
        } else {
            showValidationMessage(this, 'Tenant ID is required', false);
            validationState.tenant_id = false;
        }
    });

    if (emailInput) emailInput.addEventListener('input', async function() {
        if (this.value.length > 0) {
            const isUnique = await checkUnique('email', this.value);
            showValidationMessage(this, isUnique ? '' : 'This email is already registered', isUnique);
        } else {
            showValidationMessage(this, '', true);
            validationState.email = true;
        }
    });

    if (form) form.addEventListener('submit', async function(e) {
        // Check all required fields
        if (tenantIdInput && !tenantIdInput.value) {
            showValidationMessage(tenantIdInput, 'Tenant ID is required', false);
            e.preventDefault();
            return;
        }
        if (nameInput && !nameInput.value) {
            showValidationMessage(nameInput, 'Name is required', false);
            e.preventDefault();
            return;
        }
        if (mobileInput && !mobileInput.value) {
            showValidationMessage(mobileInput, 'Mobile number is required', false);
            e.preventDefault();
            return;
        }
        // Check uniqueness of all fields
        const fields = [
            { input: tenantIdInput, field: 'tenant_id' },
            { input: aadhaarInput, field: 'aadhaar_number' },
            { input: mobileInput, field: 'mobile' },
            { input: pancardInput, field: 'pancard_number' },
            { input: emailInput, field: 'email' }
        ];
        let isValid = true;
        for (const { input, field } of fields) {
            if (input && input.value) {
                const unique = await checkUnique(field, input.value);
                if (!unique) {
                    showValidationMessage(input, `This ${field.replace('_', ' ')} is already registered`, false);
                    isValid = false;
                }
            }
        }
        if (!isValid) {
            e.preventDefault();
            alert('Please fix the validation errors before submitting the form.');
            return;
        }
    });
}); 