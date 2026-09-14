/**
 * Theme-Matched Light Particles Background Initialization
 * Theme colors: #12466f (Navy Blue), #00b4d8 (Light Blue/Cyan), #4a7a96 (Slate Blue)
 */
(function() {
    function startParticles() {
        if (typeof particlesJS === 'undefined') return;
        const container = document.getElementById('particles-js');
        if (!container) return;

        const isMobile = window.innerWidth < 768;

        particlesJS('particles-js', {
            "particles": {
                "number": {
                    "value": isMobile ? 45 : 85,
                    "density": {
                        "enable": true,
                        "value_area": 850
                    }
                },
                "color": {
                    "value": ["#12466f", "#00b4d8", "#4a7a96", "#20639b"]
                },
                "shape": {
                    "type": "circle"
                },
                "opacity": {
                    "value": 0.35,
                    "random": true,
                    "anim": {
                        "enable": true,
                        "speed": 1.0,
                        "opacity_min": 0.12,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3.2,
                    "random": true,
                    "anim": {
                        "enable": true,
                        "speed": 2.0,
                        "size_min": 1.2,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 140,
                    "color": "#12466f",
                    "opacity": 0.18,
                    "width": 1.0
                },
                "move": {
                    "enable": true,
                    "speed": 1.8,
                    "direction": "none",
                    "random": true,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": true,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
                }
            },
            "interactivity": {
                "detect_on": "window",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "grab"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 160,
                        "line_linked": {
                            "opacity": 0.38
                        }
                    },
                    "push": {
                        "particles_nb": 3
                    }
                }
            },
            "retina_detect": true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startParticles);
    } else {
        startParticles();
    }
})();
