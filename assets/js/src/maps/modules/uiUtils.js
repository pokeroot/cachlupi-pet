// Assuming jQuery is available globally or passed/imported if this becomes a true module system
// For now, this relies on jQuery being present as it was in the original file.

export const showFeedbackMessage = (message, type = 'success') => {
    // Remove any existing standardized feedback messages first
    jQuery('.cachilupi-feedback').remove(); // Target the new base class for removal

    const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`;
    const messageElement = jQuery('<div>')
        .addClass(feedbackClass)
        .text(message);

    // ARIA roles for accessibility
    if (type === 'error') {
        messageElement.attr('role', 'alert');
        messageElement.attr('aria-live', 'assertive');
    } else {
        messageElement.attr('role', 'status');
        messageElement.attr('aria-live', 'polite');
    }

    const $bookingForm = jQuery('.cachilupi-booking-form');
    const submitButton = document.getElementById('submit-service-request'); // Assuming submitButton might be vanilla

    if (submitButton && jQuery(submitButton).length) {
        jQuery(submitButton).after(messageElement);
    } else if ($bookingForm.length) {
        $bookingForm.prepend(messageElement);
    } else {
        jQuery('body').prepend(messageElement);
        console.warn('Submit button or booking form not found for feedback message. Appended to body.');
    }

    setTimeout(() => {
        messageElement.fadeOut('slow', () => messageElement.remove());
    }, 5000);
};

export const showCachilupiToast = (message, type = 'success', duration = 4000) => {
    // Remover toasts existentes para evitar acumulación
    jQuery('.cachilupi-toast-notification').remove();

    const toast = jQuery('<div></div>')
        .addClass('cachilupi-toast-notification')
        .addClass(type) // success, error, info
        .text(message);

    jQuery('body').append(toast);

    setTimeout(() => {
        toast.addClass('show');
    }, 100);


    if (duration > 0) {
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }
};

export const showError = (fieldElement, message) => {
    let $fieldElement = jQuery(fieldElement); // Ensure jQuery object
    let $formGroup = $fieldElement.closest('.form-group');
    if (!$formGroup.length) $formGroup = $fieldElement.parent();

    let $targetElement = $fieldElement;
    if ($fieldElement.hasClass('geocoder-container')) {
        $targetElement = $fieldElement.find('.mapboxgl-ctrl-geocoder--input');
    }

    let $existingError = $formGroup.find('.error-message');
    if (!$existingError.length) {
        const $errorSpan = jQuery('<span>').addClass('error-message').text(message);
        if ($fieldElement.hasClass('geocoder-container')) {
            $fieldElement.after($errorSpan);
        } else {
            $targetElement.after($errorSpan);
        }
    } else {
        $existingError.text(message);
    }
    $targetElement.addClass('input-error');
    $formGroup.find('label').addClass('label-error');
};

export const hideError = (fieldElement) => {
    let $fieldElement = jQuery(fieldElement); // Ensure jQuery object
    let $formGroup = $fieldElement.closest('.form-group');
    if (!$formGroup.length) $formGroup = $fieldElement.parent();

    let $targetElement = $fieldElement;
    if ($fieldElement.hasClass('geocoder-container')) {
        $targetElement = $fieldElement.find('.mapboxgl-ctrl-geocoder--input');
    }

    $formGroup.find('.error-message').remove();
    if ($fieldElement.hasClass('geocoder-container')) {
        $fieldElement.next('.error-message').remove();
    }
    $targetElement.removeClass('input-error');
    $formGroup.find('label').removeClass('label-error');
};

// Included showGlobalToast as it was distinct in the original file,
// though very similar to showCachilupiToast. Consolidate if appropriate.
export const showGlobalToast = (message, type = 'info', duration = 4000) => {
    jQuery('.cachilupi-toast-notification').remove();
    const toast = jQuery('<div>').addClass('cachilupi-toast-notification').addClass(type).text(message).appendTo('body');
    // toast.width(); // Force reflow - can be problematic
    setTimeout(() => toast.addClass('show'), 10); // Ensure transition by adding class after element is in DOM
    setTimeout(() => {
        toast.removeClass('show');
        setTimeout(() => toast.remove(), 500);
    }, duration);
};
