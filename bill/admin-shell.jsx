// admin-shell.jsx — น้องเก็บบิล: ผู้ดูแล — chrome, stats, shared bits. → window.AdminShell
const { useState: aUseState, useEffect: aUseEffect, useRef: aUseRef } = React;
const A = { Btn: UI.Btn, Card: UI.Card, Baht: UI.Baht, StageBadge: UI.StageBadge, CAT: UI.CAT, TONE: UI.TONE };

// ── stats over the current cycle ────────────────────────────────
function computeStats() {
  const occ = Store.occupied();
  const cur = Store.current;
  let waterSubmitted = 0, waterWait = 0, waterReview = 0, waterReject = 0;
  let elecWait = 0, slipReview = 0, unpaid = 0, paid = 0;
  let collected = 0, billed = 0;
  let overdue = 0, overdueAmt = 0;
  occ.forEach(r => {
    const b = Store.curBill(r.id); const s = Store.stageOf(b); const t = Store.totals(b);
    if (b.water.status !== 'wait') waterSubmitted++;
    if (s === 'water_wait') waterWait++;
    if (s === 'water_review') waterReview++;
    if (s === 'water_reject') waterReject++;
    if (s === 'elec_wait') elecWait++;
    if (s === 'slip_review') slipReview++;
    if (s === 'unpaid') unpaid++;
    if (s === 'paid') { paid++; collected += t.total; }
    if (b.issued) billed += t.total;
    // cross-cycle arrears
    Store.cycles.forEach(c => {
      if (c.id === cur) return;
      const pb = Store.bill(c.id, r.id);
      if (pb && pb.issued && pb.payment.status !== 'paid' && pb.overdue) { overdue++; overdueAmt += Store.totals(pb).total; }
    });
  });
  return { total: occ.length, waterSubmitted, waterWait, waterReview, waterReject, elecWait, slipReview, unpaid, paid, collected, billed, overdue, overdueAmt };
}

// ── sidebar ─────────────────────────────────────────────────────
function Sidebar({ view, setView, stats }) {
  const items = [
    { k: 'dashboard', icon: NK.Grid, label: 'ภาพรวม' },
    { k: 'water', icon: NK.Drop, label: 'ตรวจค่าน้ำ', badge: stats.waterReview },
    { k: 'elec', icon: NK.Bolt, label: 'กรอกค่าไฟ', badge: stats.elecWait },
    { k: 'slip', icon: NK.Wallet, label: 'ตรวจสลิป', badge: stats.slipReview },
    { k: 'rooms', icon: NK.Building, label: 'ห้อง & ตั้งค่า' },
  ];
  return (
    <aside style={{ width: 236, flex: 'none', background: '#fff', borderRight: '1px solid var(--border)', display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ padding: '18px 18px 14px', display: 'flex', alignItems: 'center', gap: 10, borderBottom: '1px solid var(--border)' }}>
        <UI.Brand size={36} />
        <UI.Wordmark size={16} />
      </div>
      <nav style={{ flex: 1, padding: '12px 12px', display: 'flex', flexDirection: 'column', gap: 3 }}>
        <div style={{ font: '600 11px var(--font-body)', color: 'var(--fg-subtle)', letterSpacing: '.05em', textTransform: 'uppercase', padding: '8px 10px 6px' }}>เมนู</div>
        {items.map(it => {
          const on = view === it.k;
          return (
            <button key={it.k} onClick={() => setView(it.k)} style={{
              display: 'flex', alignItems: 'center', gap: 11, padding: '10px 12px', borderRadius: 12, border: 'none', cursor: 'pointer', textAlign: 'left',
              background: on ? 'var(--teal-50)' : 'transparent', color: on ? 'var(--teal-700)' : 'var(--ink-600)',
              font: `${on ? 700 : 500} 14px var(--font-display)`, position: 'relative',
            }}>
              <it.icon size={20} sw={on ? 2.3 : 2} />
              <span style={{ flex: 1 }}>{it.label}</span>
              {it.badge > 0 && <span style={{ font: '700 11px var(--font-mono)', color: '#fff', background: on ? 'var(--teal-500)' : 'var(--ink-400)', minWidth: 20, height: 20, borderRadius: 999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 6px' }}>{it.badge}</span>}
            </button>
          );
        })}
      </nav>
      <div style={{ padding: 12, borderTop: '1px solid var(--border)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px' }}>
          <div style={{ width: 36, height: 36, borderRadius: '50%', background: 'linear-gradient(135deg,#34ABA2,#0C7A72)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', font: '700 14px var(--font-display)', flex: 'none' }}>ป</div>
          <div style={{ flex: 1, minWidth: 0 }}><div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-900)' }}>คุณเปี่ยมสุข</div><div style={{ font: '500 11px var(--font-body)', color: 'var(--fg-subtle)' }}>ผู้ดูแลหอพัก</div></div>
          <button onClick={() => { if (confirm('รีเซ็ตข้อมูลเดโมทั้งหมด?')) Store.resetDemo(); }} title="รีเซ็ตเดโม" style={{ width: 30, height: 30, borderRadius: 8, border: 'none', background: 'var(--bg-subtle)', color: 'var(--ink-500)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><NK.Refresh size={15} /></button>
        </div>
      </div>
    </aside>
  );
}

// ── top bar ─────────────────────────────────────────────────────
function TopBar({ title, sub, right }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '18px 28px', borderBottom: '1px solid var(--border)', background: 'rgba(255,255,255,.7)', backdropFilter: 'blur(10px)', position: 'sticky', top: 0, zIndex: 10 }}>
      <div>
        <h1 style={{ margin: 0, font: '800 22px var(--font-display)', color: 'var(--ink-900)', letterSpacing: '-.01em' }}>{title}</h1>
        {sub && <div style={{ font: '500 13px var(--font-body)', color: 'var(--fg-muted)', marginTop: 2 }}>{sub}</div>}
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        {right}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 14px', background: 'var(--teal-50)', borderRadius: 999, color: 'var(--teal-700)' }}>
          <NK.Calendar size={16} /><span style={{ font: '700 13px var(--font-display)' }}>{Store.cycles.find(c => c.id === Store.current).label}</span>
        </div>
      </div>
    </div>
  );
}

// ── KPI stat card ───────────────────────────────────────────────
function Stat({ icon: Icon, label, value, sub, tone = 'muted', cat, onClick, progress }) {
  const c = cat ? UI.CAT[cat] : null;
  const t = UI.TONE[tone];
  const fg = c ? c.fg : t.fg;
  const bg = c ? c.bg : t.bg;
  return (
    <button onClick={onClick} style={{ textAlign: 'left', background: '#fff', border: 'none', borderRadius: 18, padding: 18, boxShadow: 'var(--shadow-sm)', cursor: onClick ? 'pointer' : 'default', display: 'flex', flexDirection: 'column', gap: 10, transition: 'box-shadow .2s, transform .15s' }}
      onMouseEnter={e => { if (onClick) { e.currentTarget.style.boxShadow = 'var(--shadow-md)'; e.currentTarget.style.transform = 'translateY(-2px)'; } }}
      onMouseLeave={e => { e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; e.currentTarget.style.transform = 'none'; }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ width: 38, height: 38, borderRadius: 11, background: bg, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center' }}><Icon size={21} /></div>
        {onClick && <NK.Chevron size={17} style={{ color: 'var(--ink-300)' }} />}
      </div>
      <div>
        <div style={{ font: '800 26px var(--font-display)', color: 'var(--ink-900)', letterSpacing: '-.01em', display: 'flex', alignItems: 'baseline', gap: 6 }}>{value}{sub && <span style={{ font: '500 13px var(--font-body)', color: 'var(--fg-subtle)' }}>{sub}</span>}</div>
        <div style={{ font: '500 13px var(--font-body)', color: 'var(--fg-muted)', marginTop: 2 }}>{label}</div>
      </div>
      {progress != null && (
        <div style={{ height: 6, background: 'var(--bg-subtle)', borderRadius: 99, overflow: 'hidden' }}>
          <div style={{ height: '100%', width: `${progress}%`, background: fg, borderRadius: 99, transition: 'width .4s' }} />
        </div>
      )}
    </button>
  );
}

// ── reusable section card ───────────────────────────────────────
function Panel({ title, icon: Icon, count, action, children, style }) {
  return (
    <div style={{ background: '#fff', borderRadius: 20, boxShadow: 'var(--shadow-sm)', overflow: 'hidden', ...style }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '16px 20px', borderBottom: '1px solid var(--ink-100)' }}>
        {Icon && <Icon size={19} style={{ color: 'var(--teal-600)' }} />}
        <h2 style={{ margin: 0, font: '700 15.5px var(--font-display)', color: 'var(--ink-900)' }}>{title}</h2>
        {count != null && <span style={{ font: '700 12px var(--font-mono)', color: 'var(--teal-700)', background: 'var(--teal-50)', padding: '2px 9px', borderRadius: 999 }}>{count}</span>}
        <div style={{ flex: 1 }} />
        {action}
      </div>
      <div>{children}</div>
    </div>
  );
}

// ── empty state ─────────────────────────────────────────────────
function Empty({ icon: Icon = NK.CheckCircle, title, sub }) {
  return (
    <div style={{ padding: '48px 24px', textAlign: 'center', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
      <div style={{ width: 56, height: 56, borderRadius: '50%', background: 'var(--success-bg)', color: 'var(--success)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><Icon size={28} /></div>
      <div style={{ font: '700 15px var(--font-display)', color: 'var(--ink-800)' }}>{title}</div>
      {sub && <div style={{ font: '400 13px var(--font-body)', color: 'var(--fg-muted)' }}>{sub}</div>}
    </div>
  );
}

// ── modal ───────────────────────────────────────────────────────
function Modal({ title, children, onClose, width = 440 }) {
  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 100, background: 'rgba(14,42,45,.4)', backdropFilter: 'blur(3px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
      <div onClick={e => e.stopPropagation()} style={{ width, maxWidth: '100%', maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 22, boxShadow: 'var(--shadow-lg)', animation: 'nk-pop .2s var(--ease-out)' }}>
        {title && <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '18px 20px', borderBottom: '1px solid var(--ink-100)' }}>
          <h3 style={{ margin: 0, font: '700 17px var(--font-display)', color: 'var(--ink-900)' }}>{title}</h3>
          <button onClick={onClose} style={{ width: 32, height: 32, borderRadius: '50%', border: 'none', background: 'var(--bg-subtle)', color: 'var(--ink-600)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><NK.X size={17} /></button>
        </div>}
        <div style={{ padding: 20 }}>{children}</div>
      </div>
    </div>
  );
}

// ── reject reason modal ─────────────────────────────────────────
function RejectModal({ title, presets, onConfirm, onClose }) {
  const [reason, setReason] = aUseState('');
  return (
    <Modal title={title} onClose={onClose}>
      <p style={{ margin: '0 0 12px', font: '400 13.5px/1.6 var(--font-body)', color: 'var(--fg-muted)' }}>เลือกเหตุผล หรือพิมพ์ข้อความถึงผู้เช่า ระบบจะแจ้งกลับผ่าน LINE ทันที</p>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginBottom: 12 }}>
        {presets.map(p => (
          <button key={p} onClick={() => setReason(p)} style={{ font: '600 12.5px var(--font-body)', padding: '7px 13px', borderRadius: 999, border: 'none', cursor: 'pointer', background: reason === p ? 'var(--danger-bg)' : 'var(--bg-subtle)', color: reason === p ? 'var(--danger)' : 'var(--ink-600)' }}>{p}</button>
        ))}
      </div>
      <textarea value={reason} onChange={e => setReason(e.target.value)} placeholder="พิมพ์เหตุผล…" rows={3} style={{ width: '100%', resize: 'none', border: '1.5px solid var(--border)', borderRadius: 12, padding: '11px 13px', font: '400 14px var(--font-body)', color: 'var(--ink-900)', outline: 'none', boxSizing: 'border-box' }} />
      <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
        <A.Btn kind="outline" full onClick={onClose}>ยกเลิก</A.Btn>
        <A.Btn kind="primary" full style={{ background: 'var(--danger)', boxShadow: 'none' }} disabled={!reason.trim()} onClick={() => reason.trim() && onConfirm(reason.trim())}>ยืนยันตีกลับ</A.Btn>
      </div>
    </Modal>
  );
}

window.AdminShell = { computeStats, Sidebar, TopBar, Stat, Panel, Empty, Modal, RejectModal, A };
