const FIFTEEN_MINUTES_IN_SECONDS = 15 * 60;
const THIRTY_MINUTES_IN_SECONDS = 30 * 60;

export const setDuration = {
    format(seconds) {
        const totalSeconds = Math.max(0, Math.round(Number(seconds) || 0));
        const minutes = Math.floor(totalSeconds / 60);
        const remainingSeconds = String(totalSeconds % 60).padStart(2, '0');

        return `${minutes}:${remainingSeconds}`;
    },

    statusClass(seconds, canEstimate = null) {
        if (canEstimate === null) {
            return 'border-slate-200 bg-slate-50 text-slate-700';
        }

        if (!canEstimate) {
            return 'border-slate-200 bg-slate-50 text-red-700';
        }

        const totalSeconds = Math.max(0, Number(seconds) || 0);

        if (totalSeconds <= FIFTEEN_MINUTES_IN_SECONDS) {
            return 'border-emerald-200 bg-emerald-50 text-emerald-900';
        }

        if (totalSeconds > THIRTY_MINUTES_IN_SECONDS) {
            return 'border-red-200 bg-red-50 text-red-900';
        }

        return 'border-amber-200 bg-amber-50 text-amber-900';
    },
};