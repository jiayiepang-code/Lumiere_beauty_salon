/**
 * Client-side Validation Library for Admin Module
 * Provides real-time form validation with user-friendly error messages
 */

const Validation = {
  /**
   * Validate required field
   */
  required: function (value, fieldName) {
    if (!value || value.toString().trim() === "") {
      return `${fieldName} is required`;
    }
    return null;
  },

  /**
   * Validate email format
   */
  email: function (value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
      return "Invalid email format";
    }
    return null;
  },

  /**
   * Validate string length
   */
  length: function (value, min, max, fieldName) {
    const len = value ? value.toString().length : 0;

    if (min !== null && len < min) {
      return `${fieldName} must be at least ${min} characters`;
    }

    if (max !== null && len > max) {
      return `${fieldName} must not exceed ${max} characters`;
    }

    return null;
  },

  /**
   * Validate numeric range
   */
  range: function (value, min, max, fieldName) {
    const num = parseFloat(value);

    if (isNaN(num)) {
      return `${fieldName} must be a number`;
    }

    if (min !== null && num < min) {
      return `${fieldName} must be at least ${min}`;
    }

    if (max !== null && num > max) {
      return `${fieldName} must not exceed ${max}`;
    }

    return null;
  },

  /**
   * Validate Malaysia phone number format
   */
  phoneNumber: function (value) {
    // Remove spaces and dashes
    const phone = value.replace(/[\s\-]/g, "");

    // Check for Malaysia format: 01X-XXXXXXX or 60XXXXXXXXX
    if (/^(01[0-9]{8,9})$/.test(phone) || /^(60[0-9]{9,10})$/.test(phone)) {
      return null;
    }

    return "Invalid phone format. Use Malaysia format (01X-XXXXXXX or 60XXXXXXXXX)";
  },

  /**
   * Validate password strength
   */
  passwordStrength: function (value) {
    const errors = [];

    if (value.length < 8) {
      errors.push("Password must be at least 8 characters long");
    }
    if (!/[A-Z]/.test(value)) {
      errors.push("Password must contain at least one uppercase letter");
    }
    if (!/[0-9]/.test(value)) {
      errors.push("Password must contain at least one number");
    }
    if (!/[!@#$%^&*(),.?":{}|<>]/.test(value)) {
      errors.push("Password must contain at least one special character");
    }

    return errors.length > 0 ? errors.join(". ") : null;
  },

  /**
   * Validate enum value
   */
  enum: function (value, allowedValues, fieldName) {
    if (!allowedValues.includes(value)) {
      return `${fieldName} must be one of: ${allowedValues.join(", ")}`;
    }
    return null;
  },

  /**
   * Validate date format
   */
  date: function (value, format = "YYYY-MM-DD") {
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(value)) {
      return `Invalid date format. Expected format: ${format}`;
    }

    const date = new Date(value);
    if (isNaN(date.getTime())) {
      return "Invalid date";
    }

    return null;
  },

  /**
   * Validate time format
   */
  time: function (value, format = "HH:MM:SS") {
    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/;
    if (!timeRegex.test(value)) {
      return `Invalid time format. Expected format: ${format}`;
    }
    return null;
  },

  /**
   * Validate decimal number
   */
  decimal: function (value, precision, scale, fieldName) {
    if (isNaN(value)) {
      return `${fieldName} must be a number`;
    }

    const parts = value.toString().split(".");
    const integerPart = parts[0].replace("-", "");
    const decimalPart = parts[1] || "";

    const totalDigits = integerPart.length + decimalPart.length;

    if (totalDigits > precision) {
      return `${fieldName} exceeds maximum precision of ${precision} digits`;
    }

    if (decimalPart.length > scale) {
      return `${fieldName} must have at most ${scale} decimal places`;
    }

    return null;
  },

  /**
   * Display error message on form field
   */
  showError: function (fieldId, errorMessage) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    // Add error class to field
    field.classList.add("error");

    // Remove existing error message
    this.clearError(fieldId);

    // Create and insert error message
    const errorDiv = document.createElement("div");
    errorDiv.className = "error-message";
    errorDiv.id = `${fieldId}-error`;
    errorDiv.textContent = errorMessage;

    // Insert after the field
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
  },

  /**
   * Clear error message from form field
   */
  clearError: function (fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    // Remove error class
    field.classList.remove("error");

    // Remove error message
    const errorDiv = document.getElementById(`${fieldId}-error`);
    if (errorDiv) {
      errorDiv.remove();
    }
  },

  /**
   * Clear all errors from form
   */
  clearAllErrors: function (formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    // Remove all error classes
    const errorFields = form.querySelectorAll(".error");
    errorFields.forEach((field) => field.classList.remove("error"));

    // Remove all error messages
    const errorMessages = form.querySelectorAll(".error-message");
    errorMessages.forEach((msg) => msg.remove());
  },

  /**
   * Display multiple errors on form
   */
  showErrors: function (errors) {
    for (const [fieldId, errorMessage] of Object.entries(errors)) {
      this.showError(fieldId, errorMessage);
    }
  },

  /**
   * Validate form with rules
   */
  validateForm: function (formData, rules) {
    const errors = {};

    for (const [field, fieldRules] of Object.entries(rules)) {
      const value = formData[field];

      for (const [rule, params] of Object.entries(fieldRules)) {
        let error = null;

        switch (rule) {
          case "required":
            error = this.required(value, params.label || field);
            break;

          case "email":
            if (value) {
              error = this.email(value);
            }
            break;

          case "length":
            if (value) {
              error = this.length(
                value,
                params.min || null,
                params.max || null,
                params.label || field
              );
            }
            break;

          case "range":
            if (value !== null && value !== undefined && value !== "") {
              error = this.range(
                value,
                params.min || null,
                params.max || null,
                params.label || field
              );
            }
            break;

          case "phone":
            if (value) {
              error = this.phoneNumber(value);
            }
            break;

          case "password":
            if (value) {
              error = this.passwordStrength(value);
            }
            break;

          case "enum":
            if (value) {
              error = this.enum(value, params.values, params.label || field);
            }
            break;

          case "date":
            if (value) {
              error = this.date(value, params.format || "YYYY-MM-DD");
            }
            break;

          case "time":
            if (value) {
              error = this.time(value, params.format || "HH:MM:SS");
            }
            break;

          case "decimal":
            if (value !== null && value !== undefined && value !== "") {
              error = this.decimal(
                value,
                params.precision || 10,
                params.scale || 2,
                params.label || field
              );
            }
            break;
        }

        if (error) {
          errors[field] = error;
          break; // Stop checking other rules for this field
        }
      }
    }

    return {
      valid: Object.keys(errors).length === 0,
      errors: errors,
    };
  },

  /**
   * Add real-time validation to form field
   */
  addFieldValidation: function (fieldId, validationFn) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.addEventListener("blur", () => {
      const error = validationFn(field.value);
      if (error) {
        this.showError(fieldId, error);
      } else {
        this.clearError(fieldId);
      }
    });

    field.addEventListener("input", () => {
      // Clear error on input
      if (field.classList.contains("error")) {
        this.clearError(fieldId);
      }
    });
  },

  /**
   * Handle API error response
   */
  handleApiError: function (error, formId = null) {
    if (error.details) {
      // Display field-specific errors
      this.showErrors(error.details);
    } else {
      // Display general error message
      this.showGeneralError(error.message, formId);
    }
  },

  /**
   * Show general error message
   */
  showGeneralError: function (message, formId = null) {
    let container = document.getElementById("error-container");

    if (!container && formId) {
      const form = document.getElementById(formId);
      if (form) {
        container = document.createElement("div");
        container.id = "error-container";
        form.insertBefore(container, form.firstChild);
      }
    }

    if (container) {
      container.className = "alert alert-error";
      container.textContent = message;
      container.style.display = "block";

      // Auto-hide after 5 seconds
      setTimeout(() => {
        container.style.display = "none";
      }, 5000);
    } else {
      // Fallback to alert
      alert(message);
    }
  },

  /**
   * Show success message
   */
  showSuccess: function (message, formId = null) {
    let container = document.getElementById("success-container");

    if (!container && formId) {
      const form = document.getElementById(formId);
      if (form) {
        container = document.createElement("div");
        container.id = "success-container";
        form.insertBefore(container, form.firstChild);
      }
    }

    if (container) {
      container.className = "alert alert-success";
      container.textContent = message;
      container.style.display = "block";

      // Auto-hide after 3 seconds
      setTimeout(() => {
        container.style.display = "none";
      }, 3000);
    }
  },
};

// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = Validation;
}
