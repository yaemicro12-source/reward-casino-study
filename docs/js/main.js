const bubbleMessages = [
    '今日も一緒に頑張ろうね♡',
    'ご褒美楽しみにしてて…？',
    '今日はスロットで運試しする？',
    'ゲームポイント、いい感じだよ。',
    '交換ポイントをためると選べる幅が広がるよ。'
];

const reelSymbols = ['7', '★', 'R', 'C', '✦', '♠', '♥'];

const state = {
    bet: 20,
    bubbleIndex: 0,
    spinLock: false
};

function setBubble(text) {
    const bubble = document.getElementById('speechBubble');
    if (!bubble) return;
    bubble.textContent = text;
}

function cycleBubble() {
    state.bubbleIndex = (state.bubbleIndex + 1) % bubbleMessages.length;
    setBubble(bubbleMessages[state.bubbleIndex]);
}

function animateGirlTap() {
    const wrap = document.getElementById('mainGirlWrap');
    if (!wrap) return;
    wrap.classList.remove('sparkle');
    void wrap.offsetWidth;
    wrap.classList.add('sparkle');
    window.setTimeout(() => wrap.classList.remove('sparkle'), 380);
}

function updateBet(delta) {
    state.bet = Math.max(10, Math.min(999, state.bet + delta));
    const target = document.getElementById('betValue');
    if (target) target.textContent = String(state.bet);
}

function spinReels() {
    if (state.spinLock) return;
    state.spinLock = true;

    const reels = Array.from(document.querySelectorAll('[data-reel]'));
    const btn = document.getElementById('spinButton');
    animateGirlTap();
    setBubble('スロット、いくよ…！');

    reels.forEach((reel, index) => {
        reel.animate([
            { transform: 'translateY(0) scale(1)', filter: 'blur(0px)' },
            { transform: 'translateY(-18px) scale(1.06)', filter: 'blur(1px)' },
            { transform: 'translateY(0) scale(1)', filter: 'blur(0px)' }
        ], {
            duration: 650 + index * 120,
            iterations: 1,
            easing: 'cubic-bezier(.2,.85,.2,1)'
        });
    });

    window.setTimeout(() => {
        reels.forEach((reel, index) => {
            const symbol = reelSymbols[Math.floor(Math.random() * reelSymbols.length)];
            reel.textContent = symbol;
            reel.style.color = index === 1 ? 'var(--pink)' : 'var(--gold)';
        });

        const win = Math.random() > 0.55;
        setBubble(win ? 'キラッと当たり！今日は運がいいね♡' : '惜しい…でも次はもっといけるよ。');
        if (btn) btn.textContent = win ? 'JACKPOT' : 'SPIN';

        state.spinLock = false;
    }, 980);
}

document.addEventListener('DOMContentLoaded', () => {
    const messagePool = document.getElementById('bubbleMessages');
    if (messagePool) {
        const messages = Array.from(messagePool.querySelectorAll('[data-message]')).map((node) => node.textContent || '');
        if (messages.length) {
            state.bubbleIndex = 0;
            setBubble(messages[0]);
            window.setInterval(() => {
                state.bubbleIndex = (state.bubbleIndex + 1) % messages.length;
                setBubble(messages[state.bubbleIndex]);
                animateGirlTap();
            }, 9000);
        }
    }

    document.querySelectorAll('[data-bet]').forEach((button) => {
        button.addEventListener('click', () => {
            updateBet(button.dataset.bet === '+' ? 10 : -10);
            animateGirlTap();
        });
    });

    const spinButton = document.getElementById('spinButton');
    if (spinButton) {
        spinButton.addEventListener('click', spinReels);
    }

    const mainGirl = document.getElementById('mainGirl');
    if (mainGirl) {
        mainGirl.addEventListener('click', () => {
            animateGirlTap();
            cycleBubble();
        });
    }

    document.querySelectorAll('.bottom-nav-item, .side-item, .icon-pill').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (element.getAttribute('href') === '#') {
                event.preventDefault();
            }
            animateGirlTap();
        });
    });
});
