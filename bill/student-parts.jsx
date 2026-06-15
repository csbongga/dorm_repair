// student.jsx — น้องเก็บบิล: นักศึกษา (LINE LIFF). Depends on Store, NK, UI, IOSDevice.
const { useState, useEffect, useRef } = React;
const { Btn, Card, Baht, StageBadge, FauxQR, Brand, Wordmark, LineRow, CAT } = UI;

// ── shared: section heading, tiny helpers ───────────────────────
const TH_MONTH = 'พฤษภาคม 2569';
function metaOf(stage) { return Store.STAGES[stage]; }

// numeric keypad (LIFF feel) ------------------------------------
function NumberPad({ onKey }) {
  const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'del'];
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 8 }}>
      {keys.map(k => (
        <button key={k} onClick={() => onKey(k)} style={{
          height: 54, borderRadius: 14, border: 'none', cursor: 'pointer',
          background: k === 'del' ? 'var(--bg-subtle)' : '#fff',
          boxShadow: k === 'del' ? 'none' : 'var(--shadow-xs)',
          font: '600 22px var(--font-mono)', color: 'var(--ink-800)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}
          onMouseDown={e => e.currentTarget.style.transform = 'scale(.95)'}
          onMouseUp={e => e.currentTarget.style.transform = 'scale(1)'}
          onMouseLeave={e => e.currentTarget.style.transform = 'scale(1)'}
        >{k === 'del' ? <NK.Back size={22} /> : k}</button>
      ))}
    </div>
  );
}

// faux captured photo of a meter ---------------------------------
function MeterPhoto({ reading, h = 150, kind = 'water' }) {
  const c = CAT[kind];
  return (
    <div style={{ height: h, borderRadius: 16, overflow: 'hidden', position: 'relative', background: `linear-gradient(145deg,${c.soft},#dfe9ea)` }}>
      <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(120% 90% at 30% 10%, rgba(255,255,255,.6), transparent)' }} />
      <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 10 }}>
        <div style={{ width: 58, height: 58, borderRadius: '50%', background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: 'var(--shadow-sm)', color: c.fg }}>
          <c.Icon size={28} />
        </div>
        {/* meter dial */}
        <div style={{ display: 'flex', gap: 3, padding: '6px 9px', background: '#15333a', borderRadius: 7, boxShadow: 'inset 0 1px 3px rgba(0,0,0,.5)' }}>
          {String(reading != null ? reading : '0000').padStart(5, '0').split('').map((d, i) => (
            <span key={i} style={{ width: 18, height: 26, borderRadius: 3, background: i >= 4 ? '#b23b2e' : '#222', color: '#fff', font: '700 17px var(--font-mono)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{d}</span>
          ))}
          <span style={{ alignSelf: 'flex-end', font: '600 9px var(--font-body)', color: c.fg === 'var(--teal-700)' ? '#9ad' : '#9ad', marginLeft: 3, marginBottom: 2 }}>m³</span>
        </div>
      </div>
      <div style={{ position: 'absolute', bottom: 8, right: 10, font: '500 10px var(--font-mono)', color: 'rgba(20,51,58,.5)' }}>IMG_{kind === 'water' ? '0426' : '0427'}.jpg</div>
    </div>
  );
}

// LIFF header ----------------------------------------------------
function LiffHeader({ room, onSwitch, onBell, bellCount }) {
  return (
    <div style={{
      paddingTop: 50, padding: '50px 16px 12px', display: 'flex', alignItems: 'center', gap: 11,
      background: 'rgba(243,248,249,.86)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)',
      borderBottom: '1px solid var(--border)', position: 'sticky', top: 0, zIndex: 20,
    }}>
      <Brand size={38} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ font: '800 16px var(--font-display)', color: 'var(--ink-900)', letterSpacing: '-.01em' }}>{Store.meta.dormName}</div>
        <div style={{ font: '500 11.5px var(--font-mono)', color: 'var(--fg-muted)' }}>ห้อง {room.no} · {room.nick}</div>
      </div>
      <button onClick={onBell} style={hBtn()} aria-label="แจ้งเตือน">
        <NK.Bell size={19} style={{ color: 'var(--ink-600)' }} />
        {bellCount > 0 && <span style={{ position: 'absolute', top: 7, right: 7, width: 8, height: 8, borderRadius: '50%', background: 'var(--danger)', border: '1.5px solid #fff' }} />}
      </button>
      <button onClick={onSwitch} style={hBtn()} aria-label="เปลี่ยนห้อง (เดโม)"><NK.Refresh size={18} style={{ color: 'var(--ink-600)' }} /></button>
    </div>
  );
}
function hBtn() { return { position: 'relative', width: 38, height: 38, borderRadius: '50%', border: 'none', background: '#fff', boxShadow: 'var(--shadow-xs)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }; }

// sub-screen header (back) --------------------------------------
function SubHeader({ title, onBack, accent }) {
  return (
    <div style={{ paddingTop: 50, padding: '50px 12px 12px', display: 'flex', alignItems: 'center', gap: 6, background: accent || 'rgba(243,248,249,.9)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)', position: 'sticky', top: 0, zIndex: 20, borderBottom: '1px solid var(--border)' }}>
      <button onClick={onBack} style={{ ...hBtn(), background: 'transparent', boxShadow: 'none' }}><NK.Back size={24} style={{ color: 'var(--ink-700)' }} /></button>
      <div style={{ font: '700 17px var(--font-display)', color: 'var(--ink-900)' }}>{title}</div>
    </div>
  );
}

// =================================================================
// LOGIN / RESIDENT PICKER (demo)
// =================================================================
function Login({ onPick }) {
  const [step, setStep] = useState('splash');
  const rooms = Store.occupied();
  return (
    <div style={{ height: '100%', overflowY: 'auto', background: 'linear-gradient(180deg,#E6F5F4 0%,#F3F8F9 40%)' }}>
      {step === 'splash' ? (
        <div style={{ minHeight: '100%', display: 'flex', flexDirection: 'column', padding: '90px 26px 40px', boxSizing: 'border-box' }}>
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' }}>
            <Brand size={88} radius={26} />
            <h1 style={{ margin: '26px 0 8px', font: '800 28px var(--font-display)', color: 'var(--ink-900)', letterSpacing: '-.02em' }}>น้องเก็บบิล</h1>
            <p style={{ margin: 0, font: '400 15px/1.6 var(--font-body)', color: 'var(--fg-muted)', textWrap: 'pretty' }}>ส่งเลขมิเตอร์ ดูใบแจ้งหนี้ และแจ้งชำระเงิน<br />ค่าหอของคุณ ได้ในที่เดียว</p>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <button onClick={() => setStep('pick')} style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, width: '100%', padding: '15px', border: 'none', borderRadius: 16, background: '#06C755', color: '#fff', font: '700 16px var(--font-display)', cursor: 'pointer', boxShadow: '0 8px 20px rgba(6,199,85,.3)' }}>
              <NK.Line size={22} />เข้าสู่ระบบด้วย LINE
            </button>
            <p style={{ margin: 0, font: '400 11.5px/1.5 var(--font-body)', color: 'var(--fg-subtle)', textAlign: 'center' }}>เปิดผ่าน LINE Official Account ของหอพัก<br />ระบบจะจดจำห้องของคุณอัตโนมัติ</p>
          </div>
        </div>
      ) : (
        <div style={{ padding: '70px 18px 30px' }}>
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '600 11px var(--font-body)', color: 'var(--teal-700)', background: 'var(--teal-50)', padding: '5px 11px', borderRadius: 999, marginBottom: 12 }}><NK.Sparkle size={13} />โหมดเดโม · เลือกห้องเพื่อทดลอง</div>
          <h2 style={{ margin: '0 0 4px', font: '800 22px var(--font-display)', color: 'var(--ink-900)' }}>คุณคือผู้เช่าห้องไหน?</h2>
          <p style={{ margin: '0 0 16px', font: '400 13.5px var(--font-body)', color: 'var(--fg-muted)' }}>แต่ละห้องอยู่ในสถานะต่างกัน ลองสวมบทบาทดูได้เลย</p>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 9 }}>
            {rooms.map(r => {
              const b = Store.curBill(r.id); const stage = Store.stageOf(b);
              return (
                <button key={r.id} onClick={() => onPick(r.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', textAlign: 'left', background: '#fff', border: 'none', borderRadius: 16, padding: '12px 14px', boxShadow: 'var(--shadow-xs)', cursor: 'pointer' }}>
                  <div style={{ width: 42, height: 42, borderRadius: 12, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none', font: '700 15px var(--font-mono)' }}>{r.no}</div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ font: '700 14.5px var(--font-display)', color: 'var(--ink-900)' }}>{r.nick} <span style={{ font: '400 12px var(--font-body)', color: 'var(--fg-subtle)' }}>· {r.name}</span></div>
                    <div style={{ marginTop: 4 }}><StageBadge stage={stage} size="sm" /></div>
                  </div>
                  <NK.Chevron size={18} style={{ color: 'var(--ink-300)', flex: 'none' }} />
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

// =================================================================
// HOME (resident dashboard)
// =================================================================
const HERO = {
  water_wait:   { tone: 'water',   icon: NK.Drop,    title: 'ถึงเวลาส่งเลขมิเตอร์น้ำ', body: b => `เปิดจด ${Store.meta.readWindow} · เลขครั้งก่อน ${b.water.prev} หน่วย`, cta: 'ถ่ายรูป & ส่งเลขน้ำ', go: 'water' },
  water_reject: { tone: 'danger',  icon: NK.Alert,   title: 'เลขน้ำถูกตีกลับ', body: () => 'ผู้ดูแลขอให้ส่งใหม่ แตะเพื่อดูเหตุผลและส่งอีกครั้ง', cta: 'ส่งเลขน้ำใหม่', go: 'water' },
  water_review: { tone: 'info',    icon: NK.Clock,   title: 'ส่งเลขน้ำแล้ว รอตรวจสอบ', body: b => `คุณส่งเลข ${b.water.curr} หน่วย · ผู้ดูแลกำลังตรวจรูปกับตัวเลข`, cta: null },
  elec_wait:    { tone: 'info',    icon: NK.Receipt, title: 'กำลังรวมบิลเดือนนี้', body: () => 'ค่าน้ำผ่านแล้ว ผู้ดูแลกำลังลงค่าไฟ อีกสักครู่บิลจะพร้อม', cta: null },
  unpaid:       { tone: 'pay',     icon: NK.Wallet,  title: 'บิลเดือนนี้พร้อมชำระ', body: b => `ครบกำหนด ${b.due}`, cta: 'ดูบิล & ชำระเงิน', go: 'invoice', amount: true },
  overdue:      { tone: 'danger',  icon: NK.Alert,   title: 'เลยกำหนดชำระแล้ว', body: b => `ครบกำหนดเมื่อ ${b.due} รบกวนชำระโดยเร็ว`, cta: 'ดูบิล & ชำระเงิน', go: 'invoice', amount: true },
  slip_review:  { tone: 'info',    icon: NK.Clock,   title: 'ส่งสลิปแล้ว รอตรวจสอบ', body: () => 'ผู้ดูแลกำลังตรวจยอดเงิน ปกติไม่เกิน 1 วันทำการ', cta: null },
  paid:         { tone: 'success', icon: NK.CheckCircle, title: 'ชำระครบแล้ว ขอบคุณค่ะ', body: b => `รับชำระเมื่อ ${b.payment.paidAt}`, cta: 'ดูใบเสร็จ', go: 'invoice' },
};

function Home({ room, onGo }) {
  const b = Store.curBill(room.id);
  const stage = Store.stageOf(b);
  const h = HERO[stage] || HERO.water_review;
  const t = UI.TONE[h.tone] || CAT[h.tone] || UI.TONE.info;
  const accent = CAT[h.tone] ? CAT[h.tone] : null;
  const fg = accent ? accent.fg : (UI.TONE[h.tone] || UI.TONE.info).fg;
  const bg = accent ? accent.bg : (UI.TONE[h.tone] || UI.TONE.info).bg;
  const tot = Store.totals(b);
  const arrears = room.arrears ? Store.bill('2569-04', room.id) : null;
  const showArrears = arrears && arrears.payment.status !== 'paid';

  return (
    <div style={{ padding: '14px 16px 26px', display: 'flex', flexDirection: 'column', gap: 14 }}>
      {showArrears && (
        <div onClick={() => onGo('invoice', '2569-04')} style={{ display: 'flex', alignItems: 'center', gap: 10, background: 'var(--danger-bg)', borderRadius: 14, padding: '11px 13px', cursor: 'pointer' }}>
          <NK.Alert size={20} style={{ color: 'var(--danger)', flex: 'none' }} />
          <div style={{ flex: 1 }}><div style={{ font: '700 13px var(--font-display)', color: 'var(--danger)' }}>มียอดค้างจากเดือน เม.ย.</div><div style={{ font: '500 11.5px var(--font-mono)', color: 'var(--ink-600)' }}>฿{Store.money(Store.totals(arrears).total)} · เกินกำหนดแล้ว</div></div>
          <NK.Chevron size={16} style={{ color: 'var(--danger)' }} />
        </div>
      )}

      {/* HERO status card */}
      <div style={{ borderRadius: 24, padding: 18, background: '#fff', boxShadow: 'var(--shadow-md)', position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', top: -30, right: -20, width: 130, height: 130, borderRadius: '50%', background: bg, opacity: .5 }} />
        <div style={{ position: 'relative' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
            <div style={{ width: 40, height: 40, borderRadius: 12, background: bg, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><h.icon size={22} /></div>
            <div><div style={{ font: '500 11px var(--font-body)', color: 'var(--fg-muted)' }}>บิลประจำเดือน {TH_MONTH}</div><StageBadge stage={stage} size="sm" /></div>
          </div>
          <h2 style={{ margin: '0 0 6px', font: '800 21px/1.25 var(--font-display)', color: 'var(--ink-900)', letterSpacing: '-.01em' }}>{h.title}</h2>
          {h.amount && <div style={{ margin: '2px 0 8px' }}><Baht n={tot.total} size={30} weight={700} /></div>}
          <p style={{ margin: 0, font: '400 13.5px/1.55 var(--font-body)', color: 'var(--fg-muted)' }}>{h.body(b)}</p>
          {h.cta && <div style={{ marginTop: 14 }}><Btn full kind={h.tone === 'danger' ? 'primary' : 'primary'} icon={<h.icon size={18} />} onClick={() => onGo(h.go)}>{h.cta}</Btn></div>}
        </div>
      </div>

      {/* meter summary */}
      <Card pad={4}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 12px 6px' }}>
          <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)' }}>สรุปมิเตอร์เดือนนี้</div>
          {b.issued && <button onClick={() => onGo('invoice')} style={{ font: '600 12px var(--font-body)', color: 'var(--teal-600)', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 2 }}>ดูใบแจ้งหนี้<NK.Chevron size={14} /></button>}
        </div>
        <div style={{ display: 'flex', padding: '0 12px 12px', gap: 10 }}>
          <MeterMini cat="water" units={tot.wUnits} prev={b.water.prev} curr={b.water.curr} status={b.water.status} />
          <MeterMini cat="elec" units={tot.eUnits} prev={b.elec.prev} curr={b.elec.curr} status={b.elec.entered ? 'verified' : 'wait'} />
        </div>
      </Card>

      {/* quick links */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        <QuickLink icon={NK.Receipt} label="ใบแจ้งหนี้" sub="เดือนนี้" onClick={() => onGo('invoice')} disabled={!b.issued} />
        <QuickLink icon={NK.Clock} label="ประวัติการชำระ" sub="ย้อนหลัง" onClick={() => onGo('history')} />
      </div>

      <p style={{ margin: '4px 6px 0', font: '400 11px/1.5 var(--font-body)', color: 'var(--fg-subtle)', textAlign: 'center' }}>น้องเก็บบิล · ระบบจัดการบิลหอพัก เพื่อความโปร่งใส ตรวจสอบได้</p>
    </div>
  );
}

function MeterMini({ cat, units, prev, curr, status }) {
  const c = CAT[cat];
  const done = status === 'verified';
  return (
    <div style={{ flex: 1, borderRadius: 14, background: c.soft, padding: 12 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 8 }}>
        <div style={{ width: 26, height: 26, borderRadius: 8, background: c.bg, color: c.fg, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><c.Icon size={15} /></div>
        <span style={{ font: '700 12.5px var(--font-display)', color: 'var(--ink-800)' }}>{c.label}</span>
      </div>
      {curr != null ? (
        <>
          <div style={{ font: '700 22px var(--font-mono)', color: c.fg }}>{units}<span style={{ font: '500 11px var(--font-body)', color: 'var(--fg-muted)' }}> หน่วย</span></div>
          <div style={{ font: '500 10.5px var(--font-mono)', color: 'var(--fg-subtle)', marginTop: 2 }}>{prev} → {curr}</div>
        </>
      ) : (
        <div style={{ font: '600 12px var(--font-body)', color: 'var(--fg-subtle)', padding: '6px 0' }}>{cat === 'water' ? 'ยังไม่ได้ส่งเลข' : 'รอผู้ดูแลลงเลข'}</div>
      )}
    </div>
  );
}

function QuickLink({ icon: Icon, label, sub, onClick, disabled }) {
  return (
    <button onClick={disabled ? undefined : onClick} style={{ display: 'flex', alignItems: 'center', gap: 11, background: '#fff', border: 'none', borderRadius: 16, padding: '13px 14px', boxShadow: 'var(--shadow-xs)', cursor: disabled ? 'default' : 'pointer', opacity: disabled ? .5 : 1, textAlign: 'left' }}>
      <div style={{ width: 36, height: 36, borderRadius: 11, background: 'var(--teal-50)', color: 'var(--teal-600)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><Icon size={19} /></div>
      <div><div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-900)' }}>{label}</div><div style={{ font: '500 11px var(--font-body)', color: 'var(--fg-subtle)' }}>{sub}</div></div>
    </button>
  );
}

// faux bank transfer slip (shared by student pay + admin verify)
function SlipImage({ amount, h = 200 }) {
  return (
    <div style={{ height: h, background: 'linear-gradient(160deg,#EAF1F2,#dde7e8)', position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
      <div style={{ width: 46, height: 46, borderRadius: '50%', background: '#06C755', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff' }}><NK.Check size={26} /></div>
      <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)' }}>โอนเงินสำเร็จ</div>
      <div style={{ font: '800 22px var(--font-mono)', color: 'var(--ink-900)' }}>฿{Store.money(amount)}.00</div>
      <div style={{ font: '500 10.5px var(--font-mono)', color: 'var(--fg-muted)' }}>30 พ.ค. 69 · 11:18 น.</div>
      <div style={{ position: 'absolute', bottom: 7, font: '500 9.5px var(--font-mono)', color: 'var(--fg-subtle)' }}>slip_payment.jpg</div>
    </div>
  );
}

window.StudentParts = { NumberPad, MeterPhoto, SlipImage, LiffHeader, SubHeader, Login, Home, metaOf };
