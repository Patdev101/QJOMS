// 1234.5 → "1,234.50"
export const plain = (n) => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// 1234.5 → "₱1,234.50" (negative: "−₱1,234.50")
export const peso = (n) => (Number(n) < 0 ? '−₱' : '₱') + plain(Math.abs(Number(n) || 0));
