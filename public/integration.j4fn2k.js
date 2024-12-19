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

    function getDomainRoot() {
        const hostname = window.location.hostname;
        // Handle localhost separately
        if (hostname === 'localhost' || hostname === '127.0.0.1') {
            return hostname;
        }
        const parts = hostname.split('.');
        if (parts.length > 2) {
            return '.' + parts.slice(-2).join('.');
        }
        return '.' + hostname;
    }

    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        
        const cookieOptions = [
            name + "=" + (value || ""),
            "expires=" + date.toUTCString(),
            "path=/",
            "domain=" + getDomainRoot(),
            "SameSite=Lax"
        ];

        // Add Secure flag if on HTTPS
        if (window.location.protocol === 'https:') {
            cookieOptions.push('Secure');
        }

        const cookieString = cookieOptions.join('; ');
        console.log('Setting cookie:', cookieString);
        document.cookie = cookieString;
    }

    function getCookie(name) {
        console.log('Getting cookie:', name);
        console.log('All cookies:', document.cookie);
        console.log('Domain root:', getDomainRoot());
        
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
        
        // Get the API URL based on the current domain
        const apiUrl = window.location.protocol + '//' + window.location.host;
                
        fetch(apiUrl + '/api/track-visit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                partner_id: partnerId,
                visitor_id: visitorId,
                url: window.location.href
            })
        })
        .then(response => response.json())
        .then(data => console.log('Track response:', data))
        .catch(error => console.error('Track error:', error));
    }

    // Main tracking logic
    function init() {
        console.log('Initializing tracking script');
        console.log('Current hostname:', window.location.hostname);
        console.log('Domain root:', getDomainRoot());
        
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