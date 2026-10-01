/* ================================================
   StudentHub — register.js
   Frontend Validation for Registration Form
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrationForm');
    
    if (!form) return;

    const fullname = document.getElementById('fullname');
    const email = document.getElementById('email');
    const mobile = document.getElementById('mobile');
    const course = document.getElementById('course');
    const year = document.getElementById('year');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm-password');
    const terms = document.getElementById('terms');
    const genderMale = document.getElementById('gender-male');
    const genderFemale = document.getElementById('gender-female');
    const genderOther = document.getElementById('gender-other');
    const passwordStrength = document.getElementById('password-strength');

    // Regular Expressions
    const nameRegex = /^[A-Za-z\s]{3,50}$/;
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const mobileRegex = /^[0-9]{10}$/;
    
    // Helper function to show errors
    function showError(input, errorId, message) {
        const errorElement = document.getElementById(errorId);
        if (errorElement) {
            errorElement.textContent = message;
        }
        if(input && input.classList) input.classList.add('input-error');
    }

    // Helper function to clear errors
    function clearError(input, errorId) {
        const errorElement = document.getElementById(errorId);
        if (errorElement) {
            errorElement.textContent = '';
        }
        if(input && input.classList) input.classList.remove('input-error');
    }

    // Validation functions
    function validateName() {
        if (!nameRegex.test(fullname.value.trim())) {
            showError(fullname, 'fullname-error', 'Name must be 3-50 characters long and contain only letters.');
            return false;
        }
        clearError(fullname, 'fullname-error');
        return true;
    }

    function validateEmail() {
        if (!emailRegex.test(email.value.trim())) {
            showError(email, 'email-error', 'Please enter a valid email address.');
            return false;
        }
        clearError(email, 'email-error');
        return true;
    }

    function validateMobile() {
        if (!mobileRegex.test(mobile.value.trim())) {
            showError(mobile, 'mobile-error', 'Mobile number must be exactly 10 digits.');
            return false;
        }
        clearError(mobile, 'mobile-error');
        return true;
    }

    function validateCourse() {
        if (course.value === '') {
            showError(course, 'course-error', 'Please select a course.');
            return false;
        }
        clearError(course, 'course-error');
        return true;
    }

    function validateYear() {
        if (year.value === '') {
            showError(year, 'year-error', 'Please select a year.');
            return false;
        }
        clearError(year, 'year-error');
        return true;
    }

    function validateGender() {
        if (!genderMale.checked && !genderFemale.checked && !genderOther.checked) {
            showError(null, 'gender-error', 'Please select your gender.');
            return false;
        }
        clearError(null, 'gender-error');
        return true;
    }

    function validatePassword() {
        const val = password.value;
        if (val.length < 8) {
            showError(password, 'password-error', 'Password must be at least 8 characters long.');
            passwordStrength.textContent = 'Strength: Weak';
            passwordStrength.className = 'password-strength weak';
            return false;
        }
        
        let strength = 'Weak';
        let className = 'weak';
        const hasUpper = /[A-Z]/.test(val);
        const hasLower = /[a-z]/.test(val);
        const hasNum = /[0-9]/.test(val);
        const hasSpecial = /[^A-Za-z0-9]/.test(val);

        const checks = [hasUpper, hasLower, hasNum, hasSpecial].filter(Boolean).length;
        
        if (checks >= 3 && val.length >= 8) {
            strength = 'Strong';
            className = 'strong';
        } else if (checks >= 2 && val.length >= 8) {
            strength = 'Medium';
            className = 'medium';
        }
        
        passwordStrength.textContent = `Strength: ${strength}`;
        passwordStrength.className = `password-strength ${className}`;
        
        clearError(password, 'password-error');
        return true;
    }

    function validateConfirmPassword() {
        if (confirmPassword.value !== password.value || confirmPassword.value === '') {
            showError(confirmPassword, 'confirm-password-error', 'Passwords do not match.');
            return false;
        }
        clearError(confirmPassword, 'confirm-password-error');
        return true;
    }

    function validateTerms() {
        if (!terms.checked) {
            showError(terms, 'terms-error', 'You must agree to the terms and conditions.');
            return false;
        }
        clearError(terms, 'terms-error');
        return true;
    }

    // Real-time validation
    fullname.addEventListener('input', validateName);
    email.addEventListener('input', validateEmail);
    mobile.addEventListener('input', validateMobile);
    course.addEventListener('change', validateCourse);
    year.addEventListener('change', validateYear);
    password.addEventListener('input', () => {
        validatePassword();
        if(confirmPassword.value) validateConfirmPassword();
    });
    confirmPassword.addEventListener('input', validateConfirmPassword);
    
    const genderRadios = document.querySelectorAll('input[name="gender"]');
    genderRadios.forEach(radio => radio.addEventListener('change', validateGender));
    terms.addEventListener('change', validateTerms);

    // Form submission
    form.addEventListener('submit', function (e) {
        const isNameValid = validateName();
        const isEmailValid = validateEmail();
        const isMobileValid = validateMobile();
        const isCourseValid = validateCourse();
        const isYearValid = validateYear();
        const isGenderValid = validateGender();
        const isPasswordValid = validatePassword();
        const isConfirmPasswordValid = validateConfirmPassword();
        const isTermsValid = validateTerms();

        if (!(isNameValid && isEmailValid && isMobileValid && isCourseValid && isYearValid && isGenderValid && isPasswordValid && isConfirmPasswordValid && isTermsValid)) {
            e.preventDefault(); // Prevent form submission if validation fails
        }
    });
});
