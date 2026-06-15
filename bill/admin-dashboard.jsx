// admin-dashboard.jsx — Dashboard + room grid + room drawer. → window.AdminDash
const { Stat, Panel, Empty, RejectModal, A: AS } = window.AdminShell;
const { useState: dUseState } = React;

// status tone → soft cell styling
function cellTone(stage) {
  const meta = Store.STAGES[stage];
  const t = UI.TONE[meta.tone] || UI.TONE.muted;
  return { fg: t.fg, bg: t.bg, meta };
}

// ── room status grid (by floor) ─────────────────────────────────
function RoomGrid({ onOpen }) {
  const floors = [1, 2, 3, 4];
  return (
    <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 14 }}>
      {floors.map(f => (
        <div key={f}>
          <div style={{ font: '600 11px var(--font-body)', color: 'var(--fg-subtle)', letterSpacing: '.04em', marginBottom: 8, paddingLeft: 2 }}>ชั้น {f}</div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10 }}>
            {Store.rooms.filter(r => r.floor === f).map(r => {
              const b = Store.curBill(r.id); const stage = Store.stageOf(b);
              const { fg, bg, meta } = cellTone(stage);
              const tot = b ? Store.totals(b) : null;
              const vacant = r.vacant;
              return (
                <button key={r.id} onClick={() => !vacant && onOpen(r.id)} style={{
                  textAlign: 'left', border: 'none', borderRadius: 14, padding: '12px 13px', cursor: vacant ? 'default' : 'pointer',
                  background: vacant ? 'var(--bg-subtle)' : '#fff', boxShadow: vacant ? 'none' : 'var(--shadow-xs)',
                  borderLeft: `4px solid ${vacant ? 'var(--ink-200)' : fg}`, opacity: vacant ? .6 : 1,
                  transition: 'box-shadow .18s, transform .15s',
                }}
                  onMouseEnter={e => { if (!vacant) { e.currentTarget.style.boxShadow = 'var(--shadow-md)'; e.currentTarget.style.transform = 'translateY(-2px)'; } }}
                  onMouseLeave={e => { e.currentTarget.style.boxShadow = vacant ? 'none' : 'var(--shadow-xs)'; e.currentTarget.style.transform = 'none'; }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ font: '800 16px var(--font-mono)', color: 'var(--ink-900)' }}>{r.no}</span>
                    <span style={{ width: 9, height: 9, borderRadius: '50%', background: vacant ? 'var(--ink-300)' : fg }} />
                  </div>
                  <div style={{ font: '500 11.5px var(--font-body)', color: 'var(--fg-muted)', marginTop: 3, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{vacant ? 'ห้องว่าง' : r.nick}</div>
                  <div style={{ marginTop: 8, font: `700 11px var(--font-body)`, color: vacant ? 'var(--fg-subtle)' : fg, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{meta.short}</div>
                  {!vacant && b.issued && <div style={{ font: '600 11px var(--font-mono)', color: 'var(--fg-subtle)', marginTop: 2 }}>฿{Store.money(tot.total)}</div>}
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}

// ── legend ──────────────────────────────────────────────────────
function Legend() {
  const items = [
    { tone: 'danger', label: 'ต้องตาม / ค้าง' },
    { tone: 'warning', label: 'รอดำเนินการ' },
    { tone: 'info', label: 'รอชำระ' },
    { tone: 'success', label: 'เสร็จแล้ว' },
  ];
  return (
    <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap' }}>
      {items.map(i => <span key={i.tone} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '500 12px var(--font-body)', color: 'var(--fg-muted)' }}><UI.Dot tone={i.tone} size={9} />{i.label}</span>)}
    </div>
  );
}

// ── task list item ──────────────────────────────────────────────
function TaskRow({ icon: Icon, cat, tone, label, count, onClick }) {
  const c = cat ? UI.CAT[cat] : null; const t = UI.TONE[tone];
  const fg = c ? c.fg : t.fg, bg = c ? c.bg : t.bg;
  return (
    <button onClick={onClick} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', padding: '13px 20px', border: 'none', borderBottom: '1px solid var(--ink-100)', background: 'transparent', cursor: 'pointer', textAlign: 'left' }}
      onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-subtle)'} onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
      <div style={{ width: 38, height: 38, borderRadius: 11, background: bg, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><Icon size={20} /></div>
      <div style={{ flex: 1 }}><div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)' }}>{label}</div></div>
      <span style={{ font: '800 18px var(--font-mono)', color: count > 0 ? fg : 'var(--ink-300)' }}>{count}</span>
      <NK.Chevron size={17} style={{ color: 'var(--ink-300)' }} />
    </button>
  );
}

// ── DASHBOARD ───────────────────────────────────────────────────
function Dashboard({ stats, setView, onOpenRoom }) {
  const collectPct = stats.billed > 0 ? Math.round(stats.collected / stats.billed * 100) : 0;
  const waterPct = Math.round(stats.waterSubmitted / stats.total * 100);
  const todo = stats.waterReview + stats.elecWait + stats.slipReview;
  return (
    <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 18, maxWidth: 1160, margin: '0 auto' }}>
      {/* greeting */}
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', flexWrap: 'wrap', gap: 10 }}>
        <div>
          <h2 style={{ margin: 0, font: '800 24px var(--font-display)', color: 'var(--ink-900)' }}>สวัสดีค่ะ คุณเปี่ยมสุข 💚</h2>
          <p style={{ margin: '4px 0 0', font: '400 14px var(--font-body)', color: 'var(--fg-muted)' }}>วันนี้มี <b style={{ color: 'var(--teal-700)' }}>{todo} งาน</b>ที่รอคุณตรวจ และอยู่ในช่วงจดมิเตอร์ ({Store.meta.readWindow})</p>
        </div>
      </div>

      {/* KPI row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))', gap: 14 }}>
        <Stat icon={NK.Drop} cat="water" label="ส่งเลขน้ำแล้ว" value={`${stats.waterSubmitted}/${stats.total}`} progress={waterPct} onClick={() => setView('water')} />
        <Stat icon={NK.Bolt} cat="elec" label="รอกรอกค่าไฟ" value={stats.elecWait} sub="ห้อง" tone="warning" onClick={() => setView('elec')} />
        <Stat icon={NK.Wallet} tone="warning" label="รอตรวจสลิป" value={stats.slipReview} sub="ห้อง" onClick={() => setView('slip')} />
        <Stat icon={NK.CheckCircle} tone="success" label={`เก็บเงินแล้ว ${collectPct}%`} value={`฿${Store.money(stats.collected)}`} progress={collectPct} />
        <Stat icon={NK.Alert} tone="danger" label="ค้างส่งเลข / ค้างชำระ" value={stats.waterWait + stats.waterReject + stats.overdue} sub="ห้อง" tone2 />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.55fr', gap: 18, alignItems: 'start' }}>
        {/* tasks */}
        <Panel title="งานที่ต้องทำ" icon={NK.List} count={todo}>
          {todo === 0 ? <Empty title="เคลียร์ครบแล้ว!" sub="ไม่มีงานค้างตรวจในตอนนี้" /> : (
            <>
              <TaskRow icon={NK.Drop} cat="water" tone="warning" label="ตรวจสอบค่าน้ำ (เทียบรูป)" count={stats.waterReview} onClick={() => setView('water')} />
              <TaskRow icon={NK.Bolt} cat="elec" tone="warning" label="กรอกเลขมิเตอร์ไฟ" count={stats.elecWait} onClick={() => setView('elec')} />
              <TaskRow icon={NK.Wallet} tone="warning" label="ตรวจสลิปการโอนเงิน" count={stats.slipReview} onClick={() => setView('slip')} />
            </>
          )}
          {(stats.waterWait + stats.waterReject) > 0 && (
            <div style={{ padding: '13px 20px', borderTop: '1px solid var(--ink-100)', display: 'flex', alignItems: 'center', gap: 10 }}>
              <NK.Alert size={18} style={{ color: 'var(--danger)' }} />
              <div style={{ flex: 1, font: '500 12.5px var(--font-body)', color: 'var(--ink-600)' }}><b style={{ color: 'var(--danger)' }}>{stats.waterWait + stats.waterReject} ห้อง</b> ยังค้างส่งเลขน้ำ / ถูกตีกลับ</div>
            </div>
          )}
          {stats.overdue > 0 && (
            <div style={{ padding: '13px 20px', borderTop: '1px solid var(--ink-100)', display: 'flex', alignItems: 'center', gap: 10 }}>
              <NK.Clock size={18} style={{ color: 'var(--danger)' }} />
              <div style={{ flex: 1, font: '500 12.5px var(--font-body)', color: 'var(--ink-600)' }}><b style={{ color: 'var(--danger)' }}>{stats.overdue} ห้อง</b> ค้างชำระยกมา · ฿{Store.money(stats.overdueAmt)}</div>
            </div>
          )}
        </Panel>

        {/* room grid */}
        <Panel title="สถานะรายห้อง" icon={NK.Grid} action={<Legend />}>
          <RoomGrid onOpen={onOpenRoom} />
        </Panel>
      </div>
    </div>
  );
}

// ── ROOM DRAWER (slide-in, full control) ────────────────────────
function RoomDrawer({ roomId, onClose, setView }) {
  const [, force] = dUseState(0);
  const [elecVal, setElecVal] = dUseState('');
  const [reject, setReject] = dUseState(null); // 'water' | 'slip'
  const r = Store.roomById(roomId);
  const b = Store.curBill(roomId);
  const stage = Store.stageOf(b);
  const tot = Store.totals(b);
  const meta = Store.STAGES[stage];
  const refresh = () => force(x => x + 1);

  const eNum = parseFloat(elecVal);
  const eUnits = !isNaN(eNum) ? Math.max(0, eNum - b.elec.prev) : null;

  return (
    <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 90, background: 'rgba(14,42,45,.4)', backdropFilter: 'blur(3px)', display: 'flex', justifyContent: 'flex-end' }}>
      <div onClick={e => e.stopPropagation()} style={{ width: 420, maxWidth: '100%', height: '100%', background: 'var(--bg-canvas)', boxShadow: 'var(--shadow-lg)', overflowY: 'auto', animation: 'nk-slide .26s var(--ease-out)' }}>
        {/* header */}
        <div style={{ padding: '20px 22px', background: '#fff', borderBottom: '1px solid var(--border)', position: 'sticky', top: 0, zIndex: 2 }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 13 }}>
            <div style={{ width: 50, height: 50, borderRadius: 14, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', font: '800 18px var(--font-mono)', flex: 'none' }}>{r.no}</div>
            <div style={{ flex: 1 }}>
              <div style={{ font: '800 18px var(--font-display)', color: 'var(--ink-900)' }}>ห้อง {r.no} · {r.nick}</div>
              <div style={{ font: '500 12.5px var(--font-body)', color: 'var(--fg-muted)' }}>{r.name}</div>
              <div style={{ marginTop: 6 }}><UI.StageBadge stage={stage} /></div>
            </div>
            <button onClick={onClose} style={{ width: 32, height: 32, borderRadius: '50%', border: 'none', background: 'var(--bg-subtle)', color: 'var(--ink-600)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><NK.X size={17} /></button>
          </div>
        </div>

        <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
          {/* contextual action */}
          {stage === 'water_review' && (
            <div style={{ background: '#fff', borderRadius: 18, padding: 16, boxShadow: 'var(--shadow-sm)' }}>
              <div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 7 }}><NK.Drop size={18} style={{ color: UI.CAT.water.fg }} />ตรวจสอบค่าน้ำ</div>
              <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                <div style={{ flex: 1 }}><window.StudentParts.MeterPhoto reading={b.water.curr} kind="water" h={120} /><div style={{ textAlign: 'center', font: '500 10.5px var(--font-body)', color: 'var(--fg-subtle)', marginTop: 5 }}>รูปจากผู้เช่า</div></div>
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 8 }}>
                  <KV k="เลขที่พิมพ์" v={`${b.water.curr}`} big />
                  <KV k="ครั้งก่อน" v={`${b.water.prev}`} />
                  <KV k="ใช้ไป" v={`${tot.wUnits} หน่วย`} accent={UI.CAT.water.fg} />
                  <KV k="เป็นเงิน" v={`฿${Store.money(tot.wAmt)}`} />
                </div>
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <AS.Btn kind="outline" full style={{ color: 'var(--danger)' }} icon={<NK.X size={17} />} onClick={() => setReject('water')}>ตีกลับ</AS.Btn>
                <AS.Btn kind="primary" full icon={<NK.Check size={17} />} onClick={() => { Store.verifyWater(roomId); refresh(); }}>ยืนยันค่าน้ำ</AS.Btn>
              </div>
            </div>
          )}
          {stage === 'elec_wait' && (
            <div style={{ background: '#fff', borderRadius: 18, padding: 16, boxShadow: 'var(--shadow-sm)' }}>
              <div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 7 }}><NK.Bolt size={18} style={{ color: UI.CAT.elec.fg }} />กรอกเลขมิเตอร์ไฟ</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                <div style={{ flex: 'none', font: '500 12px var(--font-mono)', color: 'var(--fg-muted)' }}>ครั้งก่อน<br /><b style={{ font: '700 16px var(--font-mono)', color: 'var(--ink-800)' }}>{b.elec.prev}</b></div>
                <NK.Arrow size={18} style={{ color: 'var(--ink-300)' }} />
                <input autoFocus value={elecVal} onChange={e => setElecVal(e.target.value.replace(/[^0-9.]/g, ''))} placeholder="เลขล่าสุด" inputMode="decimal" style={{ flex: 1, border: '1.5px solid var(--border)', borderRadius: 12, padding: '12px 14px', font: '700 18px var(--font-mono)', color: 'var(--ink-900)', outline: 'none', width: '100%', boxSizing: 'border-box' }} onFocus={e => e.target.style.borderColor = UI.CAT.elec.solid} onBlur={e => e.target.style.borderColor = 'var(--border)'} />
              </div>
              {eUnits != null && eNum >= b.elec.prev && <div style={{ font: '600 13px var(--font-body)', color: UI.CAT.elec.fg, marginBottom: 12 }}>ใช้ไป {eUnits} หน่วย · ฿{Store.money(eUnits * b.rate.elec)}</div>}
              <AS.Btn kind="primary" full disabled={!(eUnits != null && eNum >= b.elec.prev)} style={{ opacity: (eUnits != null && eNum >= b.elec.prev) ? 1 : .5 }} icon={<NK.Check size={17} />} onClick={() => { if (eNum >= b.elec.prev) { Store.enterElec(roomId, eNum); refresh(); } }}>บันทึก & ออกบิล</AS.Btn>
            </div>
          )}
          {stage === 'slip_review' && (
            <div style={{ background: '#fff', borderRadius: 18, padding: 16, boxShadow: 'var(--shadow-sm)' }}>
              <div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 7 }}><NK.Wallet size={18} style={{ color: 'var(--teal-600)' }} />ตรวจสลิปการโอน</div>
              <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                <div style={{ flex: 1, borderRadius: 12, overflow: 'hidden', boxShadow: 'var(--shadow-xs)' }}><window.StudentParts.SlipImage amount={tot.total} h={150} /></div>
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 8 }}>
                  <KV k="ยอดบิล" v={`฿${Store.money(tot.total)}`} big />
                  <KV k="ยอดในสลิป" v={`฿${Store.money(tot.total)}`} accent="var(--success)" />
                  <KV k="แจ้งเมื่อ" v={b.payment.notifiedAt} />
                </div>
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <AS.Btn kind="outline" full style={{ color: 'var(--danger)' }} icon={<NK.X size={17} />} onClick={() => setReject('slip')}>ตีกลับ</AS.Btn>
                <AS.Btn kind="primary" full icon={<NK.Check size={17} />} onClick={() => { Store.confirmPayment(roomId); refresh(); }}>ยืนยันรับชำระ</AS.Btn>
              </div>
            </div>
          )}
          {['water_wait', 'water_reject'].includes(stage) && (
            <div style={{ background: 'var(--danger-bg)', borderRadius: 16, padding: 16, display: 'flex', gap: 11 }}>
              <NK.Clock size={20} style={{ color: 'var(--danger)', flex: 'none', marginTop: 1 }} />
              <div><div style={{ font: '700 13.5px var(--font-display)', color: 'var(--danger)' }}>{stage === 'water_reject' ? 'รอผู้เช่าส่งเลขน้ำใหม่' : 'รอผู้เช่าส่งเลขมิเตอร์น้ำ'}</div><div style={{ font: '400 12.5px/1.5 var(--font-body)', color: 'var(--ink-600)', marginTop: 2 }}>{stage === 'water_reject' ? b.water.reason : `เปิดให้จดในช่วง ${Store.meta.readWindow}`}</div></div>
            </div>
          )}
          {['unpaid', 'overdue'].includes(stage) && (
            <div style={{ background: stage === 'overdue' ? 'var(--danger-bg)' : '#DCEEF9', borderRadius: 16, padding: 16, display: 'flex', gap: 11 }}>
              <NK.Wallet size={20} style={{ color: stage === 'overdue' ? 'var(--danger)' : '#2E7CB8', flex: 'none', marginTop: 1 }} />
              <div><div style={{ font: '700 13.5px var(--font-display)', color: stage === 'overdue' ? 'var(--danger)' : '#2E7CB8' }}>{stage === 'overdue' ? 'ค้างชำระ — เลยกำหนด' : 'ออกบิลแล้ว รอผู้เช่าชำระ'}</div><div style={{ font: '400 12.5px var(--font-body)', color: 'var(--ink-600)', marginTop: 2 }}>ครบกำหนด {b.due}</div></div>
            </div>
          )}
          {stage === 'paid' && (
            <div style={{ background: 'var(--success-bg)', borderRadius: 16, padding: 16, display: 'flex', gap: 11, alignItems: 'center' }}>
              <NK.CheckCircle size={22} style={{ color: 'var(--success)', flex: 'none' }} />
              <div><div style={{ font: '700 13.5px var(--font-display)', color: 'var(--success)' }}>ชำระครบแล้ว</div><div style={{ font: '400 12.5px var(--font-body)', color: 'var(--ink-600)' }}>รับชำระเมื่อ {b.payment.paidAt}</div></div>
            </div>
          )}

          {/* bill breakdown */}
          <div style={{ background: '#fff', borderRadius: 18, padding: '6px 18px', boxShadow: 'var(--shadow-sm)' }}>
            <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)', padding: '12px 0 4px' }}>รายละเอียดบิล {Store.cycles.find(c => c.id === Store.current).short}</div>
            <UI.LineRow cat="water" title="ค่าน้ำ" sub={b.water.curr != null ? `${tot.wUnits} หน่วย × ฿${b.rate.water}` : 'ยังไม่ส่งเลข'} right={<UI.Baht n={tot.wAmt} size={14} />} />
            <div style={{ height: 1, background: 'var(--ink-100)' }} />
            <UI.LineRow cat="elec" title="ค่าไฟ" sub={b.elec.curr != null ? `${tot.eUnits} หน่วย × ฿${b.rate.elec}` : 'ยังไม่กรอก'} right={<UI.Baht n={tot.eAmt} size={14} />} />
            <div style={{ height: 1, background: 'var(--ink-100)' }} />
            <UI.LineRow cat="rent" title="ค่าเช่า" sub={r.type === 'corner' ? 'ห้องหัวมุม' : 'ห้องมาตรฐาน'} right={<UI.Baht n={tot.rent} size={14} />} />
            <div style={{ borderTop: '1.5px dashed var(--border)', margin: '4px 0', padding: '12px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)' }}>รวม</span>
              <UI.Baht n={tot.total} size={19} weight={700} color="var(--teal-700)" />
            </div>
          </div>

          {/* contact */}
          <div style={{ display: 'flex', gap: 10 }}>
            <div style={{ flex: 1, background: '#fff', borderRadius: 14, padding: '12px 14px', boxShadow: 'var(--shadow-xs)', display: 'flex', alignItems: 'center', gap: 9 }}>
              <NK.Phone size={16} style={{ color: 'var(--teal-600)' }} /><span style={{ font: '600 12.5px var(--font-mono)', color: 'var(--ink-700)' }}>{r.phone}</span>
            </div>
          </div>
        </div>
      </div>

      {reject === 'water' && <RejectModal title="ตีกลับค่าน้ำ" presets={['รูปเบลอ อ่านเลขไม่ชัด', 'ตัวเลขไม่ตรงกับรูป', 'ถ่ายไม่เห็นหน้าปัด', 'ส่งผิดห้อง']} onClose={() => setReject(null)} onConfirm={(reason) => { Store.rejectWater(roomId, reason); setReject(null); refresh(); }} />}
      {reject === 'slip' && <RejectModal title="ตีกลับสลิป" presets={['ยอดเงินไม่ตรง', 'สลิปไม่ชัด', 'โอนผิดบัญชี', 'ยังไม่พบเงินเข้า']} onClose={() => setReject(null)} onConfirm={(reason) => { Store.rejectSlip(roomId, reason); setReject(null); refresh(); }} />}
    </div>
  );
}

function KV({ k, v, accent, big }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 8 }}>
      <span style={{ font: '500 12px var(--font-body)', color: 'var(--fg-muted)', flex: 'none' }}>{k}</span>
      <span style={{ font: `${big ? 700 : 600} ${big ? 17 : 13.5}px var(--font-mono)`, color: accent || 'var(--ink-900)', textAlign: 'right' }}>{v}</span>
    </div>
  );
}

window.AdminDash = { Dashboard, RoomDrawer };
