export const initTabs = () => {
    const tabWrapper = document.querySelector('.nav-tab-wrapper');
    if (tabWrapper) {
        document.addEventListener('click', (event) => {
            const clickedTab = event.target.closest('.nav-tab-wrapper a.nav-tab');
            if (!clickedTab) return;

            event.preventDefault();

            const panel = clickedTab.closest('.cachilupi-driver-panel'); // Assuming this is the overall container

            // Remove active class from all tabs within this specific tab wrapper
            const allTabsInWrapper = tabWrapper.querySelectorAll('a.nav-tab');
            allTabsInWrapper.forEach(tab => tab.classList.remove('nav-tab-active'));

            // Hide all tab content within this specific panel or globally if panel not found
            let tabContents;
            if (panel) {
                tabContents = panel.querySelectorAll('.tab-content');
            } else {
                tabContents = document.querySelectorAll('.tab-content'); // Fallback
            }
            tabContents.forEach(content => content.style.display = 'none');

            // Add active class to the clicked tab
            clickedTab.classList.add('nav-tab-active');

            // Show the corresponding tab content
            const activeTabContentID = clickedTab.getAttribute('href');
            if (activeTabContentID && activeTabContentID.startsWith('#')) {
                const activeContentElement = document.getElementById(activeTabContentID.substring(1));
                if (activeContentElement) {
                    activeContentElement.style.display = 'block';
                } else {
                    console.error(`Tab content not found for ID: ${activeTabContentID}`);
                }
            } else {
                 console.error(`Invalid href attribute for tab: ${activeTabContentID}`);
            }
        });
    }
};

export const showDriverPanelFeedback = (message, type = 'success') => {
    let feedbackContainer = document.getElementById('driver-panel-feedback');
    if (!feedbackContainer) {
        const mainTitle = document.querySelector('.wrap h1');
        if (mainTitle) {
            feedbackContainer = document.createElement('div');
            feedbackContainer.id = 'driver-panel-feedback';
            feedbackContainer.style.marginBottom = '15px';
            mainTitle.after(feedbackContainer);
        } else {
            console.error("Feedback container #driver-panel-feedback not found and couldn't be created.");
            return;
        }
    }

    feedbackContainer.innerHTML = ''; // Clear previous messages

    const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`;
    const messageElement = document.createElement('div');
    messageElement.classList.add(...feedbackClass.split(' ')); // Add multiple classes if space-separated
    messageElement.textContent = message;

    if (type === 'error') {
        messageElement.setAttribute('role', 'alert');
        messageElement.setAttribute('aria-live', 'assertive');
    } else {
        messageElement.setAttribute('role', 'status');
        messageElement.setAttribute('aria-live', 'polite');
    }

    feedbackContainer.appendChild(messageElement);

    // FadeOut and remove simulation
    messageElement.style.transition = 'opacity 0.5s ease-out';
    setTimeout(() => {
        messageElement.style.opacity = '0';
        setTimeout(() => {
            messageElement.remove();
        }, 500); // Time for fade out transition
    }, 5000); // Time message is visible
};

export const showNewRequestNotification = (count) => {
    let notificationArea = document.getElementById('driver-new-requests-notification');
    if (!notificationArea) {
        const wrap = document.querySelector('.wrap');
        notificationArea = document.createElement('div');
        notificationArea.id = 'driver-new-requests-notification';
        if (wrap) {
            wrap.prepend(notificationArea);
        } else {
            document.body.prepend(notificationArea); // Fallback
            console.warn("'.wrap' container not found for new request notification, prepended to body.");
        }
    }

    const messageText = count > 1
        ? `Hay ${count} nuevas solicitudes pendientes.`
        : `Hay ${count} nueva solicitud pendiente.`;

    notificationArea.innerHTML = `${messageText} <a href="#" onclick="location.reload(); return false;">Actualizar página</a>`;

    // SlideDown simulation (basic visibility, can be enhanced with CSS transitions)
    notificationArea.style.display = 'none'; // Start hidden
    requestAnimationFrame(() => { // Ensure display:none is applied before transition starts
        notificationArea.style.transition = 'opacity 0.3s ease-in-out, max-height 0.3s ease-in-out';
        notificationArea.style.opacity = '0';
        notificationArea.style.maxHeight = '0px';
        notificationArea.style.display = 'block'; // Make it part of the layout flow

        requestAnimationFrame(() => { // Next frame to apply visible styles
            notificationArea.style.opacity = '1';
            notificationArea.style.maxHeight = '50px'; // Adjust as needed for content
        });
    });
};
