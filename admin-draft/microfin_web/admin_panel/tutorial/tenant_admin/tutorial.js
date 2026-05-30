/**
 * MicroFin Admin Tutorial
 * Interactive guided tour for first-time admin users
 * Uses Shepherd.js for tour functionality
 */

(function() {
    'use strict';

    // Check if tutorial should start (either from PHP flag or sessionStorage)
    const tutorialActive = typeof window.startTutorial !== 'undefined' && window.startTutorial;
    const tutorialProgress = sessionStorage.getItem('tutorialProgress');

    if (!tutorialActive && !tutorialProgress) {
        return;
    }

    // Set progress if starting fresh
    if (tutorialActive && !tutorialProgress) {
        sessionStorage.setItem('tutorialProgress', 'started');
    }

    // Load Shepherd.js dynamically
    function loadShepherd(callback) {
        const shepherdCss = document.createElement('link');
        shepherdCss.rel = 'stylesheet';
        shepherdCss.href = 'https://cdn.jsdelivr.net/npm/shepherd.js@11.0.0/dist/css/shepherd.css';
        document.head.appendChild(shepherdCss);

        const shepherdJs = document.createElement('script');
        shepherdJs.src = 'https://cdn.jsdelivr.net/npm/shepherd.js@11.0.0/dist/js/shepherd.min.js';
        shepherdJs.onload = callback;
        document.head.appendChild(shepherdJs);
    }

    // Initialize tutorial when DOM is ready
    function initTutorial() {
        loadShepherd(function() {
            if (typeof Shepherd === 'undefined') {
                console.error('Shepherd.js failed to load');
                return;
            }

            // Get branding colors from window (injected by PHP)
            const branding = window.tutorialBranding || {
                primaryColor: '#dc2626',
                secondaryColor: '#991b1b',
                fontFamily: 'Inter',
                textMain: '#0f172a',
                bgCard: '#ffffff'
            };

            // Apply custom CSS variables for Shepherd
            const style = document.createElement('style');
            style.textContent = `
                .shepherd-element {
                    font-family: ${branding.fontFamily}, sans-serif;
                }
                .shepherd-button {
                    background-color: ${branding.primaryColor} !important;
                    color: white !important;
                    border: none !important;
                    border-radius: 6px !important;
                    padding: 8px 16px !important;
                    font-weight: 500 !important;
                    transition: background-color 0.2s !important;
                }
                .shepherd-button:hover {
                    background-color: ${branding.secondaryColor} !important;
                }
                .shepherd-button.shepherd-button-secondary {
                    background-color: transparent !important;
                    color: ${branding.textMain} !important;
                    border: 1px solid ${branding.primaryColor} !important;
                }
                .shepherd-button.shepherd-button-secondary:hover {
                    background-color: ${branding.primaryColor} !important;
                    color: white !important;
                }
                .shepherd-header {
                    background-color: ${branding.bgCard} !important;
                    border-bottom: 1px solid #e2e8f0 !important;
                }
                .shepherd-title {
                    color: ${branding.textMain} !important;
                    font-weight: 600 !important;
                }
                .shepherd-text {
                    color: ${branding.textMain} !important;
                    line-height: 1.6 !important;
                }
                .shepherd-content {
                    background-color: ${branding.bgCard} !important;
                    border-radius: 12px !important;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2) !important;
                }
                .shepherd-arrow::before {
                    background-color: ${branding.bgCard} !important;
                }
                .shepherd-cancel-icon {
                    color: ${branding.textMain} !important;
                }
            `;
            document.head.appendChild(style);

            // Create tour instance
            const tour = new Shepherd.Tour({
                defaultStepOptions: {
                    classes: 'shepherd-theme-custom',
                    scrollTo: { behavior: 'smooth', block: 'center' },
                    cancelIcon: {
                        enabled: true
                    }
                },
                useModalOverlay: true
            });

            // Define tour steps
            const steps = [
                {
                    id: 'welcome',
                    text: `
                        <div style="text-align: center; padding: 10px 0;">
                            <h3 style="margin: 0 0 10px; color: ${branding.primaryColor};">Welcome to MicroFin!</h3>
                            <p style="margin: 0;">Let's take a quick tour of your admin dashboard to help you get started.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Start Tour',
                            action: tour.next
                        },
                        {
                            text: 'Skip',
                            action: tour.complete,
                            classes: 'shepherd-button-secondary'
                        }
                    ]
                },
                {
                    id: 'dashboard-hero',
                    attachTo: {
                        element: '[data-tutorial-step="dashboard-hero"]',
                        on: 'bottom'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Dashboard Overview</h3>
                            <p style="margin: 0;">This is your workspace overview showing your company's health status, plan details, and key metrics at a glance.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'stats-grid',
                    attachTo: {
                        element: '[data-tutorial-step="stats-grid"]',
                        on: 'top'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Key Metrics</h3>
                            <p style="margin: 0;">Track your active clients, daily collections, overdue loans, and available capital from these stat cards.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'capacity-snapshot',
                    attachTo: {
                        element: '[data-tutorial-step="capacity-snapshot"]',
                        on: 'top'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Capacity Snapshot</h3>
                            <p style="margin: 0;">Monitor your client and staff capacity against your plan limits. Stay within healthy utilization levels.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'quick-actions',
                    attachTo: {
                        element: '[data-tutorial-step="quick-actions"]',
                        on: 'top'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Quick Actions</h3>
                            <p style="margin: 0;">Use these buttons to quickly manage staff, configure loan products, access policy console, and more.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'recent-audit-trail',
                    attachTo: {
                        element: '[data-tutorial-step="recent-audit-trail"]',
                        on: 'top'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Recent Audit Trail</h3>
                            <p style="margin: 0;">View recent system activities and actions performed by users in your organization.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'sidebar',
                    attachTo: {
                        element: '[data-tutorial-step="sidebar"]',
                        on: 'right'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Sidebar Navigation</h3>
                            <p style="margin: 0;">Access all your modules, staff management, loan products, funds, website, billing, and settings from here.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        },
                        {
                            text: 'Next',
                            action: tour.next
                        }
                    ]
                },
                {
                    id: 'audit-trail-link',
                    attachTo: {
                        element: '[data-tutorial-step="audit-trail-link"]',
                        on: 'right'
                    },
                    text: `
                        <div>
                            <h3 style="margin: 0 0 10px;">Audit Trail</h3>
                            <p style="margin: 0;">Click here to view all system activities and actions performed by users in your organization.</p>
                        </div>
                    `,
                    buttons: [
                        {
                            text: 'Back',
                            action: tour.back
                        }
                    ]
                }
            ];

            // Always add audit trail view step (will be shown when on audit_trail page)
            steps.push({
                id: 'audit-trail-view',
                attachTo: {
                    element: '#audit_trail',
                    on: 'top'
                },
                text: `
                    <div>
                        <h3 style="margin: 0 0 10px;">Audit Trail</h3>
                        <p style="margin: 0;">View all system activities and actions performed by users in your organization. Track who did what and when.</p>
                    </div>
                `,
                buttons: [
                    {
                        text: 'Back',
                        action: function() {
                            sessionStorage.setItem('tutorialProgress', 'sidebar');
                            window.location.href = 'admin.php';
                            return false;
                        }
                    },
                    {
                        text: 'Finish',
                        action: function() {
                            sessionStorage.removeItem('tutorialProgress');
                            tour.complete();
                        }
                    }
                ]
            });

            // Start at appropriate step based on progress
            const currentProgress = sessionStorage.getItem('tutorialProgress');
            const isAuditTrailPage = window.location.pathname.includes('admin.php') &&
                                    (window.location.search.includes('tab=audit_trail') ||
                                     window.location.hash.includes('audit_trail'));
            let startStep = 0;
            if (currentProgress === 'audit_trail' && isAuditTrailPage) {
                startStep = 8; // audit-trail-view step (after audit-trail-link)
            }

            // Add steps to tour
            steps.forEach(step => tour.addStep(step));

            // Add click handler for audit trail link during tutorial
            const auditTrailLink = document.querySelector('[data-tutorial-step="audit-trail-link"]');
            if (auditTrailLink) {
                auditTrailLink.addEventListener('click', function(e) {
                    console.log('Audit trail link clicked, current progress:', sessionStorage.getItem('tutorialProgress'));
                    if (sessionStorage.getItem('tutorialProgress') === 'started') {
                        sessionStorage.setItem('tutorialProgress', 'audit_trail');
                        console.log('Progress set to audit_trail');
                    }
                });
            }

            // Auto-advance when user navigates to correct page
            function checkPageAndAdvance() {
                const currentProgress = sessionStorage.getItem('tutorialProgress');
                const isAuditTrailPage = window.location.pathname.includes('admin.php') &&
                                        (window.location.search.includes('tab=audit_trail') ||
                                         window.location.hash.includes('audit_trail'));

                console.log('checkPageAndAdvance - progress:', currentProgress, 'isAuditTrailPage:', isAuditTrailPage, 'currentStep:', tour.currentStep ? tour.currentStep.id : 'none');

                // If we're on audit_trail page and progress is audit_trail, show audit-trail-view step
                if (isAuditTrailPage && currentProgress === 'audit_trail') {
                    const auditTrailViewStep = tour.steps.find(s => s.id === 'audit-trail-view');
                    console.log('auditTrailViewStep found:', !!auditTrailViewStep);
                    if (auditTrailViewStep && tour.currentStep.id !== 'audit-trail-view') {
                        console.log('Showing audit-trail-view step');
                        tour.show('audit-trail-view');
                    }
                }
            }

            // Check on step change
            tour.on('show', checkPageAndAdvance);

            // Check periodically (in case user navigates manually)
            setInterval(checkPageAndAdvance, 1000);

            // Handle tour completion
            tour.on('complete', function() {
                console.log('Tutorial completed');
                // Optional: Call AJAX endpoint to mark tutorial as complete
                // fetch('complete_tutorial.php', { method: 'POST' });
            });

            tour.on('cancel', function() {
                console.log('Tutorial cancelled');
            });

            // Start the tour after a short delay
            setTimeout(function() {
                tour.start();
                if (startStep > 0) {
                    tour.show(startStep);
                }
            }, 1000);
        });
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTutorial);
    } else {
        initTutorial();
    }

})();
