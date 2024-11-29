(function() {
    const PARTNER_COOKIE_NAME = 'tc_partner';
    const VISITOR_COOKIE_NAME = 'tc_visitor';
    const COOKIE_DURATION = 30; // days

    function generateVisitorId() {
        return 'v_' + Math.random().toString(36).substr(2, 9);
    }

    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = "; expires=" + date.toUTCString();
        
        // Set cookie for current domain and all subdomains
        const domain = window.location.hostname.split('.').slice(-2).join('.');
        const cookieValue = name + "=" + (value || "") + expires + "; path=/";
        
        console.log('Setting cookie:', cookieValue);
        document.cookie = cookieValue;
        
        // Also set for localhost if we're in development
        if (window.location.hostname === 'localhost') {
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }
    }

    function getCookie(name) {
        console.log('Getting cookie:', name);
        console.log('All cookies:', document.cookie);
        
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) {
                const value = c.substring(nameEQ.length, c.length);
                console.log('Found cookie value:', value);
                return value;
            }
        }
        console.log('Cookie not found');
        return null;
    }

    function trackVisit(partnerId, visitorId) {
        console.log('Tracking visit:', { partnerId, visitorId });
        
        fetch('http://localhost:8080/api/track-visit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                partner_id: partnerId,
                visitor_id: visitorId
            })
        })
        .then(response => response.json())
        .then(data => console.log('Track response:', data))
        .catch(error => console.error('Track error:', error));
    }

    // Main tracking logic
    function init() {
        console.log('Initializing tracking script');
        
        const partnerCode = getQueryParam('partner');
        console.log('Partner code from URL:', partnerCode);
        
        let visitorId = getCookie(VISITOR_COOKIE_NAME);
        console.log('Existing visitor ID:', visitorId);

        if (!visitorId) {
            visitorId = generateVisitorId();
            console.log('Generated new visitor ID:', visitorId);
            setCookie(VISITOR_COOKIE_NAME, visitorId, COOKIE_DURATION);
        }

        if (partnerCode) {
            console.log('Setting partner cookie:', partnerCode);
            setCookie(PARTNER_COOKIE_NAME, partnerCode, COOKIE_DURATION);
            trackVisit(partnerCode, visitorId);
        }
    }

    // Initialize tracking
    init();
})(); 