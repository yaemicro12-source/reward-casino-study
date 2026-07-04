document.addEventListener('DOMContentLoaded', () => {
    const timerDisplay = document.getElementById('timer-display');
    if (!timerDisplay) {
        return;
    }

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
});
