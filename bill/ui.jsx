// ui.jsx — shared primitives for น้องเก็บบิล. Depends on window.NK, colors_and_type.css. → window.UI
const { useState: useStateUI } = React;

// category accent system: น้ำ = ฟ้า(sky), ไฟ = เหลือง-ส้ม, เช่า = teal
const CAT = {
  water: { fg: '#2E7CB8', bg: '#DCEEF9', solid: '#4A9BD4', soft: '#EAF4FB', Icon: (p) => <NK.Drop {...p} />, label: 'ค่าน้ำ' },
  elec:  { fg: '#B5740E', bg: '#FBEFD6', solid: '#E0961E', soft: '#FCF6E8', Icon: (p) => <NK.Bolt {...p} />, label: 'ค่าไฟ' },
  rent:  { fg: 'var(--teal-700)', bg: 'var(--teal-50)', solid: 'var(--teal-500)', soft: '#EEF8F7', Icon: (p) => <NK.Door {...p} />, label: 'ค่าเช่า' },
  pay:   { fg: 'var(--teal-700)', bg: 'var(--teal-50)', solid: 'var(--teal-500)', soft: '#EEF8F7', Icon: (p) => <NK.Wallet {...p} />, label: 'ชำระเงิน' },
};

const TONE = {
  danger:  { fg: 'var(--danger)',  bg: 'var(--danger-bg)'  },
  warning: { fg: '#B5740E',        bg: '#FBEFD6'           },
  success: { fg: 'var(--success)', bg: 'var(--success-bg)' },
  info:    { fg: '#2E7CB8',        bg: '#DCEEF9'           },
  muted:   { fg: 'var(--fg-muted)',bg: 'var(--bg-subtle)'  },
};

// ── brand mark — clean tool logo (receipt + drop), minimal mascot ──
function Brand({ size = 36, radius }) {
  const r = radius != null ? radius : size * 0.28;
  return (
    <div style={{
      width: size, height: size, borderRadius: r, flex: 'none',
      background: 'linear-gradient(145deg,#34ABA2,#0C7A72)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      boxShadow: '0 3px 10px rgba(12,122,114,.32)', position: 'relative',
    }}>
      <svg width={size * 0.56} height={size * 0.56} viewBox="0 0 24 24" fill="none">
        <path d="M6 3.4v17.2l1.8-1.2 1.8 1.2 1.8-1.2 1.8 1.2 1.8-1.2 1.8 1.2V3.4l-1.8 1.2L13.2 3.4l-1.8 1.2L9.6 3.4 7.8 4.6 6 3.4Z"
              fill="#fff" fillOpacity=".95" />
        <path d="M9.2 9.5h5.6M9.2 12.6h5.6M9.2 15.7h3.2" stroke="#0C7A72" strokeWidth="1.3" strokeLinecap="round" />
      </svg>
      <span style={{
        position: 'absolute', right: -1, bottom: -1, width: size * 0.34, height: size * 0.34,
        borderRadius: '50%', background: '#fff',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        boxShadow: '0 1px 3px rgba(12,122,114,.3)',
      }}>
        <NK.Drop size={size * 0.2} style={{ color: '#4A9BD4' }} sw={2.4} />
      </span>
    </div>
  );
}

function Wordmark({ size = 18, color = 'var(--ink-900)', sub = true }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.05 }}>
      <span style={{ font: `800 ${size}px var(--font-display)`, color, letterSpacing: '-.01em' }}>น้องเก็บบิล</span>
      {sub && <span style={{ font: `500 ${size * 0.5}px var(--font-body)`, color: 'var(--fg-subtle)', letterSpacing: '.02em', marginTop: 2 }}>ระบบจัดการบิลหอพัก</span>}
    </div>
  );
}

// ── status pill for a bill's stage ──
function StageBadge({ stage, size = 'md' }) {
  const meta = Store.STAGES[stage] || Store.STAGES.vacant;
  const t = TONE[meta.tone] || TONE.muted;
  const pad = size === 'sm' ? '3px 9px' : '5px 11px';
  const fs = size === 'sm' ? 11.5 : 12.5;
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 6, flex: 'none',
      font: `600 ${fs}px var(--font-body)`, color: t.fg, background: t.bg,
      padding: pad, borderRadius: 999, whiteSpace: 'nowrap',
    }}>
      <span style={{ width: 7, height: 7, borderRadius: '50%', background: t.fg, flex: 'none' }} />
      {meta.label}
    </span>
  );
}

function Dot({ tone, size = 9 }) {
  const t = TONE[tone] || TONE.muted;
  return <span style={{ width: size, height: size, borderRadius: '50%', background: t.fg, flex: 'none', display: 'inline-block' }} />;
}

// ── money ──
function Baht({ n, size = 15, weight = 600, color = 'var(--ink-900)', sign = true }) {
  return (
    <span style={{ font: `${weight} ${size}px var(--font-mono)`, color, whiteSpace: 'nowrap' }}>
      {sign && <span style={{ opacity: .6, fontSize: size * 0.8 }}>฿</span>}{Store.money(n)}
    </span>
  );
}

// ── faux PromptPay QR (deterministic from a string) ──
function FauxQR({ data = '0858831907', size = 168 }) {
  const N = 25;
  // simple xorshift hash → module grid
  let h = 2166136261;
  for (let i = 0; i < data.length; i++) { h ^= data.charCodeAt(i); h = Math.imul(h, 16777619); }
  const rng = () => { h ^= h << 13; h ^= h >>> 17; h ^= h << 5; return ((h >>> 0) % 1000) / 1000; };
  const cells = [];
  const isFinder = (r, c) => (r < 7 && c < 7) || (r < 7 && c >= N - 7) || (r >= N - 7 && c < 7);
  for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) {
    if (isFinder(r, c)) continue;
    if (rng() > 0.52) cells.push([r, c]);
  }
  const m = size / N;
  const finder = (x, y) => (
    <g key={`f${x}${y}`}>
      <rect x={x * m} y={y * m} width={7 * m} height={7 * m} rx={m} fill="#0E2A2D" />
      <rect x={(x + 1) * m} y={(y + 1) * m} width={5 * m} height={5 * m} rx={m * .7} fill="#fff" />
      <rect x={(x + 2) * m} y={(y + 2) * m} width={3 * m} height={3 * m} rx={m * .5} fill="#0E2A2D" />
    </g>
  );
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ display: 'block' }}>
      <rect width={size} height={size} fill="#fff" />
      {cells.map(([r, c], i) => <rect key={i} x={c * m + m * .08} y={r * m + m * .08} width={m * .84} height={m * .84} rx={m * .22} fill="#0E2A2D" />)}
      {finder(0, 0)}{finder(N - 7, 0)}{finder(0, N - 7)}
    </svg>
  );
}

// ── generic button ──
function Btn({ children, kind = 'primary', size = 'md', full, icon, style, ...rest }) {
  const sizes = {
    sm: { font: '600 13px var(--font-body)', pad: '8px 14px', gap: 6 },
    md: { font: '700 15px var(--font-display)', pad: '12px 18px', gap: 8 },
    lg: { font: '700 16px var(--font-display)', pad: '15px 20px', gap: 9 },
  }[size];
  const kinds = {
    primary: { background: 'var(--teal-500)', color: '#fff', boxShadow: '0 6px 16px rgba(21,145,135,.28)' },
    soft:    { background: 'var(--teal-50)', color: 'var(--teal-700)' },
    ghost:   { background: 'transparent', color: 'var(--fg-default)' },
    outline: { background: '#fff', color: 'var(--fg-default)', boxShadow: 'inset 0 0 0 1.5px var(--border)' },
    danger:  { background: 'var(--danger-bg)', color: 'var(--danger)' },
    dark:    { background: 'var(--ink-900)', color: '#fff' },
  }[kind];
  return (
    <button {...rest} style={{
      display: full ? 'flex' : 'inline-flex', width: full ? '100%' : undefined,
      alignItems: 'center', justifyContent: 'center', gap: sizes.gap,
      font: sizes.font, padding: sizes.pad, border: 'none', borderRadius: 999,
      cursor: 'pointer', whiteSpace: 'nowrap', transition: 'transform .12s, box-shadow .2s, background .2s',
      ...kinds, ...style,
    }}
      onMouseDown={e => e.currentTarget.style.transform = 'scale(.97)'}
      onMouseUp={e => e.currentTarget.style.transform = 'scale(1)'}
      onMouseLeave={e => e.currentTarget.style.transform = 'scale(1)'}
    >{icon}{children}</button>
  );
}

// ── card ──
function Card({ children, style, pad = 16, onClick, hover }) {
  const [h, setH] = useStateUI(false);
  return (
    <div onClick={onClick}
      onMouseEnter={() => setH(true)} onMouseLeave={() => setH(false)}
      style={{
        background: '#fff', borderRadius: 'var(--radius-xl)',
        boxShadow: h && (hover || onClick) ? 'var(--shadow-md)' : 'var(--shadow-sm)',
        padding: pad, cursor: onClick ? 'pointer' : 'default',
        transition: 'box-shadow .2s, transform .15s',
        transform: h && (hover || onClick) ? 'translateY(-1px)' : 'none',
        ...style,
      }}>{children}</div>
  );
}

// ── a labelled line-item row for invoices ──
function LineRow({ cat, title, sub, right, rightSub }) {
  const c = CAT[cat] || CAT.rent;
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 0' }}>
      <div style={{ width: 38, height: 38, borderRadius: 11, background: c.bg, color: c.fg, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}>
        <c.Icon size={20} />
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ font: '600 14.5px var(--font-body)', color: 'var(--ink-900)' }}>{title}</div>
        {sub && <div style={{ font: '500 12px var(--font-mono)', color: 'var(--fg-muted)', marginTop: 1 }}>{sub}</div>}
      </div>
      <div style={{ textAlign: 'right' }}>
        {right}
        {rightSub && <div style={{ font: '500 11px var(--font-mono)', color: 'var(--fg-subtle)', marginTop: 1 }}>{rightSub}</div>}
      </div>
    </div>
  );
}

window.UI = { CAT, TONE, Brand, Wordmark, StageBadge, Dot, Baht, FauxQR, Btn, Card, LineRow };
