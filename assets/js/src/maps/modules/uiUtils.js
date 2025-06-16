export const showFeedbackMessage = (message, type = 'success') => {
    // Remove any existing standardized feedback messages first
    document.querySelectorAll('.cachilupi-feedback').forEach(el => el.remove());

    const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`;
    const messageElement = document.createElement('div');
    messageElement.className = feedbackClass;
    messageElement.textContent = message;

    // ARIA roles for accessibility
    if (type === 'error') {
        messageElement.setAttribute('role', 'alert');
        messageElement.setAttribute('aria-live', 'assertive');
    } else {
        messageElement.setAttribute('role', 'status');
        messageElement.setAttribute('aria-live', 'polite');
    }

    const bookingForm = document.querySelector('.cachilupi-booking-form');
    const submitButton = document.getElementById('submit-service-request');

    if (submitButton) {
        submitButton.after(messageElement);
    } else if (bookingForm) {
        bookingForm.prepend(messageElement);
    } else {
        document.body.prepend(messageElement);
        console.warn('Submit button or booking form not found for feedback message. Appended to body.');
    }

    // Simulate fadeOut
    messageElement.style.transition = 'opacity 0.5s ease-out';
    setTimeout(() => {
        messageElement.style.opacity = '0';
        setTimeout(() => {
            messageElement.remove();
        }, 500); // Time for fade out transition
    }, 5000); // Time message is visible
};


export const showToast = (message, type = 'info', duration = 4000, containerSelector = null) => {
    // Remove existing toasts to avoid accumulation
    document.querySelectorAll('.cachilupi-toast-notification').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.classList.add('cachilupi-toast-notification', type); // type can be 'success', 'error', 'info'
    toast.textContent = message;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');


    let container = document.body;
    if (containerSelector) {
        const selectedContainer = document.querySelector(containerSelector);
        if (selectedContainer) {
            container = selectedContainer;
            // Ensure container is positioned if it's not body, for absolute positioning of toast
            if (getComputedStyle(container).position === 'static') {
                container.style.position = 'relative';
            }
        } else {
            console.warn(`Toast container "${containerSelector}" not found. Appending to body.`);
        }
    }

    container.appendChild(toast);

    // Animation: Make it appear
    // Needs CSS: .cachilupi-toast-notification { opacity: 0; transform: translateY(20px); transition: opacity 0.3s, transform 0.3s; }
    // .cachilupi-toast-notification.show { opacity: 1; transform: translateY(0); }
    requestAnimationFrame(() => { // Ensure element is in DOM for transition
        toast.classList.add('show');
    });

    if (duration > 0) {
        setTimeout(() => {
            toast.classList.remove('show');
            // Remove element after transition
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, duration);
    }
    // If duration is 0 or less, it will stay until manually removed or a new toast is shown.
};


export const showError = (fieldElement, message) => {
    if (!fieldElement) return;

    const formGroup = fieldElement.closest('.form-group') || fieldElement.parentElement;
    let targetElement = fieldElement;

    if (fieldElement.classList.contains('geocoder-container')) {
        targetElement = fieldElement.querySelector('.mapboxgl-ctrl-geocoder--input') || fieldElement;
    }

    let errorSpan = formGroup.querySelector('.error-message');
    if (!errorSpan) {
        errorSpan = document.createElement('span');
        errorSpan.classList.add('error-message');
        // Insert after the fieldElement or its wrapper if it's a geocoder
        (fieldElement.classList.contains('geocoder-container') ? fieldElement : targetElement).after(errorSpan);
    }
    errorSpan.textContent = message;
    errorSpan.style.display = 'block'; // Make sure it's visible

    targetElement.classList.add('input-error');
    const label = formGroup.querySelector('label');
    if (label) {
        label.classList.add('label-error');
    }
};

export const hideError = (fieldElement) => {
    if (!fieldElement) return;

    const formGroup = fieldElement.closest('.form-group') || fieldElement.parentElement;
    let targetElement = fieldElement;

    if (fieldElement.classList.contains('geocoder-container')) {
        targetElement = fieldElement.querySelector('.mapboxgl-ctrl-geocoder--input') || fieldElement;
    }

    const errorSpan = formGroup.querySelector('.error-message');
    if (errorSpan) {
        errorSpan.remove();
    }
    // If error was inserted directly after geocoder-container
    if (fieldElement.classList.contains('geocoder-container')) {
        const directError = fieldElement.nextElementSibling;
        if (directError && directError.classList.contains('error-message')) {
            directError.remove();
        }
    }

    targetElement.classList.remove('input-error');
    const label = formGroup.querySelector('label');
    if (label) {
        label.classList.remove('label-error');
    }
};


// showCachilupiToast and showGlobalToast are now consolidated into showToast.
// For compatibility, if other modules were directly calling them,
// you might want to add these as aliases for a short period:
// export const showCachilupiToast = (message, type = 'success', duration = 4000) => showToast(message, type, duration, '.some-specific-container-if-needed');
// export const showGlobalToast = (message, type = 'info', duration = 4000) => showToast(message, type, duration);
// However, it's better to update the calling code to use showToast directly.
