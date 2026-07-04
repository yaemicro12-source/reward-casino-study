document.addEventListener('DOMContentLoaded', () => {
    // === タイマー機能 ===
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        let remaining = 25 * 60;
        let timer = null;

        const render = () => {
            const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
            const seconds = String(remaining % 60).padStart(2, '0');
            timerDisplay.textContent = `${minutes}:${seconds}`;
        };

        document.querySelectorAll('[data-timer]').forEach((button) => {
            button.addEventListener('click', () => {
                remaining = Number(button.dataset.timer) * 60;
                render();
            });
        });

        const startButton = document.getElementById('timer-start');
        const resetButton = document.getElementById('timer-reset');

        startButton?.addEventListener('click', () => {
            if (timer) {
                clearInterval(timer);
            }
            timer = setInterval(() => {
                if (remaining <= 0) {
                    clearInterval(timer);
                    timer = null;
                    return;
                }
                remaining -= 1;
                render();
            }, 1000);
        });

        resetButton?.addEventListener('click', () => {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
            remaining = 25 * 60;
            render();
        });

        render();
    }

    // === スロット回転アニメーション ===
    const spinButton = document.querySelector('[data-action="spin-slot"]');
    if (spinButton) {
        spinButton.addEventListener('click', function (e) {
            const reels = document.querySelectorAll('.slot-reel');
            reels.forEach((reel, index) => {
                setTimeout(() => {
                    reel.classList.add('pulse');
                    reel.style.animation = 'spin 0.8s ease-in-out';
                    setTimeout(() => {
                        reel.style.animation = 'none';
                        reel.classList.remove('pulse');
                    }, 800);
                }, index * 100);
            });
        });
    }

    // === ガチャ開封演出 ===
    document.querySelectorAll('.gacha-button').forEach((button) => {
        button.addEventListener('click', function () {
            const result = document.querySelector('.gacha-result');
            if (result) {
                result.classList.add('pulse');
                setTimeout(() => {
                    result.classList.remove('pulse');
                }, 1000);
            }
        });
    });

    // === カード ホバー演出 ===
    document.querySelectorAll('.card').forEach((card) => {
        card.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });
        card.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // === ナビゲーション アクティブ状態 ===
    const navLinks = document.querySelectorAll('.nav-link');
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
    navLinks.forEach((link) => {
        if (link.href.includes(currentPage)) {
            link.style.borderColor = 'var(--gold)';
            link.style.background = 'linear-gradient(135deg, rgba(255, 215, 102, 0.15), rgba(255, 215, 102, 0.08))';
            link.style.color = 'var(--gold-strong)';
            link.style.boxShadow = '0 0 10px rgba(255, 215, 102, 0.2)';
        }
    });

    // === 通知 自動消去 ===
    document.querySelectorAll('.notice').forEach((notice) => {
        setTimeout(() => {
            notice.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => {
                notice.remove();
            }, 300);
        }, 4000);
    });

    // === フォーム入力値のバリデーション表示 ===
    document.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('change', function () {
            if (this.min && Number(this.value) < Number(this.min)) {
                this.value = this.min;
            }
        });
    });

    // === ボタンリップル効果 ===
    document.querySelectorAll('.btn').forEach((btn) => {
        btn.addEventListener('mousedown', function (e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.className = 'ripple';
            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });

    // === ページ遷移時のアニメーション ===
    const links = document.querySelectorAll('a[href*="?page="]');
    links.forEach((link) => {
        link.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href.startsWith('?') || href.startsWith('./')) {
                // ページ遷移をスムーズに
                document.body.style.opacity = '0.8';
                document.body.style.transition = 'opacity 0.3s ease-out';
            }
        });
    });
});

// === スライドアウトアニメーション ===
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: rippleEffect 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes rippleEffect {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
