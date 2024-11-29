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
        document.cookie = name + "=" + (value || "") + expires + "; path=/; domain=.timecamp.com";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    function trackVisit(partnerId, visitorId) {
        fetch('/api/track-visit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                partner_id: partnerId,
                visitor_id: visitorId
            })
        }).catch(console.error);
    }

    // Main tracking logic
    function init() {
        const partnerCode = getQueryParam('partner');
        let visitorId = getCookie(VISITOR_COOKIE_NAME);

        if (!visitorId) {
            visitorId = generateVisitorId();
            setCookie(VISITOR_COOKIE_NAME, visitorId, COOKIE_DURATION);
        }

        if (partnerCode) {
            setCookie(PARTNER_COOKIE_NAME, partnerCode, COOKIE_DURATION);
            trackVisit(partnerCode, visitorId);
        }
    }

    // Initialize tracking
    init();
})(); 