// Assumes jQuery is available globally

export const initTabs = () => {
    if (jQuery('.nav-tab-wrapper').length > 0) {
        jQuery(document).on('click', '.nav-tab-wrapper a.nav-tab', function(e) {
            e.preventDefault();
            const $thisTab = jQuery(this);
            const $tabWrapper = $thisTab.closest('.nav-tab-wrapper');
            const $panel = $thisTab.closest('.cachilupi-driver-panel'); // Assuming this is the overall container

            $tabWrapper.find('a.nav-tab').removeClass('nav-tab-active');
            if ($panel.length) {
                 $panel.find('.tab-content').hide(); // More specific to the panel
            } else {
                 jQuery('.tab-content').hide(); // Fallback if panel structure isn't there
            }

            $thisTab.addClass('nav-tab-active');
            const activeTabContentID = $thisTab.attr('href');

            if (jQuery(activeTabContentID).length) {
                jQuery(activeTabContentID).show();
            } else {
                console.error(`Tab content not found for ID: ${activeTabContentID}`);
            }
        });
    }
};

export const showDriverPanelFeedback = (message, type = 'success') => {
    let $feedbackContainer = jQuery('#driver-panel-feedback');
    if (!$feedbackContainer.length) {
        const $mainTitle = jQuery('.wrap h1').first();
        if ($mainTitle.length) {
            $feedbackContainer = jQuery('<div>').attr('id', 'driver-panel-feedback').css('margin-bottom', '15px'); // Added style from original context
            $mainTitle.after($feedbackContainer);
        } else {
            console.error("Feedback container #driver-panel-feedback not found and couldn't be created.");
            return;
        }
    }

    $feedbackContainer.empty();
    const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`; // Match class from maps.js if desired
    const messageElement = jQuery('<div>')
        .addClass(feedbackClass)
        .text(message);

    if (type === 'error') {
        messageElement.attr('role', 'alert').attr('aria-live', 'assertive');
    } else {
        messageElement.attr('role', 'status').attr('aria-live', 'polite');
    }

    messageElement.appendTo($feedbackContainer);

    setTimeout(() => {
        messageElement.fadeOut('slow', () => {
            messageElement.remove();
        });
    }, 5000);
};

export const showNewRequestNotification = (count) => {
    let $notificationArea = jQuery('#driver-new-requests-notification');
    if (!$notificationArea.length) {
        // Attempt to prepend to '.wrap' which is a common WordPress admin page container
        const $wrap = jQuery('.wrap').first();
        if ($wrap.length) {
            $notificationArea = jQuery('<div id="driver-new-requests-notification"></div>').prependTo($wrap);
        } else {
            // Fallback to body, though less ideal
            $notificationArea = jQuery('<div id="driver-new-requests-notification"></div>').prependTo('body');
            console.warn("'.wrap' container not found for new request notification, prepended to body.");
        }
    }
    const messageText = count > 1
        ? `Hay ${count} nuevas solicitudes pendientes.`
        : `Hay ${count} nueva solicitud pendiente.`;

    $notificationArea.html(`${messageText} <a href="#" onclick="location.reload(); return false;">Actualizar página</a>`)
                     .slideDown();
};
