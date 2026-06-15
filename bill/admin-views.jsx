// admin-views.jsx — task surfaces: elec entry, water verify, slip verify, rooms/settings. → window.AdminViews
const { Panel: VP, Empty: VEmpty, RejectModal: VReject, A: VA } = window.AdminShell;
const { useState: vUseState } = React;
const VMeter = window.StudentParts.MeterPhoto;
const VSlip = window.StudentParts.SlipImage;

// =================================================================
// ELEC ENTRY
// =================================================================
function ElecRow({ room, refresh }) {
  const b = Store.curBill(room.id);
  const entered = b.elec.entered;
  const [val, setVal] = vUseState(entered ? String(b.elec.curr) : '');
  const [edit, setEdit] = vUseState(!entered);
  const num = parseFloat(val);
  const ok = !isNaN(num) && num >= b.elec.prev;
  const units = ok ? num - b.elec.prev : null;
  const waterPending = b.water.status !== 'verified';

  return (
    <div style={{ display: 'grid', gridTemplateColumns: '120px 90px 1fr 150px 130px', alignItems: 'center', gap: 12, padding: '13px 20px', borderBottom: '1px solid var(--ink-100)' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
        <span style={{ font: '800 15px var(--font-mono)', color: 'var(--ink-900)' }}>{room.no}</span>
        <span style={{ font: '500 12.5px var(--font-body)', color: 'var(--fg-muted)' }}>{room.nick}</span>
      </div>
      <div style={{ font: '600 14px var(--font-mono)', color: 'var(--fg-muted)' }}>{b.elec.prev}</div>
      <div>
        {edit ? (
          <input autoFocus={!entered ? false : true} value={val} onChange={e => setVal(e.target.value.replace(/[^0-9.]/g, ''))} placeholder="เลขล่าสุด" inputMode="decimal"
            style={{ width: 140, border: '1.5px solid var(--border)', borderRadius: 10, padding: '9px 12px', font: '700 15px var(--font-mono)', color: 'var(--ink-900)', outline: 'none' }}
            onFocus={e => e.target.style.borderColor = UI.CAT.elec.solid} onBlur={e => e.target.style.borderColor = 'var(--border)'} />
        ) : (
          <span style={{ font: '700 15px var(--font-mono)', color: 'var(--ink-900)' }}>{b.elec.curr}</span>
        )}
      </div>
      <div style={{ font: '600 13px var(--font-mono)', color: units != null ? UI.CAT.elec.fg : 'var(--ink-300)' }}>
        {units != null ? `${units} หน่วย · ฿${Store.money(units * b.rate.elec)}` : '—'}
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 6 }}>
        {entered && !edit ? (
          <>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, font: '600 12px var(--font-body)', color: 'var(--success)', background: 'var(--success-bg)', padding: '6px 11px', borderRadius: 999 }}><NK.Check size={14} />ลงแล้ว</span>
            <button onClick={() => setEdit(true)} style={{ width: 32, height: 32, borderRadius: 9, border: 'none', background: 'var(--bg-subtle)', color: 'var(--ink-500)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><NK.Edit size={15} /></button>
          </>
        ) : (
          <VA.Btn size="sm" disabled={!ok} style={{ opacity: ok ? 1 : .45 }} onClick={() => { if (ok) { Store.enterElec(room.id, num); setEdit(false); refresh(); } }}>บันทึก</VA.Btn>
        )}
      </div>
    </div>
  );
}

function ElecEntry({ refresh }) {
  const occ = Store.occupied();
  const done = occ.filter(r => Store.curBill(r.id).elec.entered).length;
  return (
    <div style={{ padding: 24, maxWidth: 980, margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', background: UI.CAT.elec.soft, borderRadius: 14, padding: '12px 16px' }}>
        <NK.Info size={18} style={{ color: UI.CAT.elec.fg, flex: 'none' }} />
        <div style={{ font: '400 13px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>เดินจดมิเตอร์ไฟแต่ละห้องแล้วพิมพ์ตัวเลขล่าสุด ระบบจะคำนวณหน่วยและยอดเงินให้อัตโนมัติ — เมื่อค่าน้ำผ่านและค่าไฟครบ บิลจะถูกออกทันที</div>
      </div>
      <VP title="กรอกเลขมิเตอร์ไฟ" icon={NK.Bolt} count={`${done}/${occ.length}`}>
        <div style={{ display: 'grid', gridTemplateColumns: '120px 90px 1fr 150px 130px', gap: 12, padding: '10px 20px', borderBottom: '1px solid var(--ink-100)', font: '600 11px var(--font-body)', color: 'var(--fg-subtle)', letterSpacing: '.03em', textTransform: 'uppercase' }}>
          <div>ห้อง</div><div>ครั้งก่อน</div><div>เลขล่าสุด</div><div>หน่วย · เงิน</div><div></div>
        </div>
        {occ.map(r => <ElecRow key={r.id} room={r} refresh={refresh} />)}
      </VP>
    </div>
  );
}

// =================================================================
// WATER VERIFY
// =================================================================
function WaterVerify({ refresh, onOpenRoom }) {
  const queue = Store.occupied().filter(r => Store.stageOf(Store.curBill(r.id)) === 'water_review');
  const [reject, setReject] = vUseState(null);
  return (
    <div style={{ padding: 24, maxWidth: 980, margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', background: UI.CAT.water.soft, borderRadius: 14, padding: '12px 16px' }}>
        <NK.Info size={18} style={{ color: UI.CAT.water.fg, flex: 'none' }} />
        <div style={{ font: '400 13px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>เทียบรูปถ่ายหน้าปัดที่ผู้เช่าส่งมา กับตัวเลขที่พิมพ์ หากไม่ตรงหรือรูปไม่ชัด กด "ตีกลับ" เพื่อให้ส่งใหม่</div>
      </div>
      {queue.length === 0 ? (
        <VP title="ตรวจสอบค่าน้ำ" icon={NK.Drop} count={0}><VEmpty title="ตรวจครบแล้ว" sub="ไม่มีค่าน้ำรอตรวจในตอนนี้" /></VP>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(420px, 1fr))', gap: 16 }}>
          {queue.map(r => {
            const b = Store.curBill(r.id); const tot = Store.totals(b);
            return (
              <div key={r.id} style={{ background: '#fff', borderRadius: 18, boxShadow: 'var(--shadow-sm)', overflow: 'hidden' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '14px 18px', borderBottom: '1px solid var(--ink-100)' }}>
                  <div style={{ width: 40, height: 40, borderRadius: 11, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', font: '800 15px var(--font-mono)' }}>{r.no}</div>
                  <div style={{ flex: 1 }}><div style={{ font: '700 15px var(--font-display)', color: 'var(--ink-900)' }}>{r.nick}</div><div style={{ font: '500 11.5px var(--font-mono)', color: 'var(--fg-subtle)' }}>ส่งเมื่อ {b.water.at}</div></div>
                </div>
                <div style={{ display: 'flex', gap: 14, padding: 18 }}>
                  <div style={{ flex: 1 }}><VMeter reading={b.water.curr} kind="water" h={130} /><div style={{ textAlign: 'center', font: '500 10.5px var(--font-body)', color: 'var(--fg-subtle)', marginTop: 6 }}>รูปจากผู้เช่า</div></div>
                  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 9 }}>
                    <KVv k="เลขที่พิมพ์" v={`${b.water.curr}`} big />
                    <KVv k="ครั้งก่อน" v={`${b.water.prev}`} />
                    <KVv k="ใช้ไป" v={`${tot.wUnits} หน่วย`} accent={UI.CAT.water.fg} />
                    <KVv k="เป็นเงิน" v={`฿${Store.money(tot.wAmt)}`} />
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 10, padding: '0 18px 18px' }}>
                  <VA.Btn kind="outline" full style={{ color: 'var(--danger)' }} icon={<NK.X size={16} />} onClick={() => setReject(r.id)}>ตีกลับ</VA.Btn>
                  <VA.Btn kind="primary" full icon={<NK.Check size={16} />} onClick={() => { Store.verifyWater(r.id); refresh(); }}>ยืนยันค่าน้ำ</VA.Btn>
                </div>
              </div>
            );
          })}
        </div>
      )}
      {reject && <VReject title="ตีกลับค่าน้ำ" presets={['รูปเบลอ อ่านเลขไม่ชัด', 'ตัวเลขไม่ตรงกับรูป', 'ถ่ายไม่เห็นหน้าปัด', 'ส่งผิดห้อง']} onClose={() => setReject(null)} onConfirm={(reason) => { Store.rejectWater(reject, reason); setReject(null); refresh(); }} />}
    </div>
  );
}

// =================================================================
// SLIP VERIFY
// =================================================================
function SlipVerify({ refresh }) {
  const queue = Store.occupied().filter(r => Store.stageOf(Store.curBill(r.id)) === 'slip_review');
  const [reject, setReject] = vUseState(null);
  return (
    <div style={{ padding: 24, maxWidth: 980, margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', background: 'var(--teal-50)', borderRadius: 14, padding: '12px 16px' }}>
        <NK.Info size={18} style={{ color: 'var(--teal-600)', flex: 'none' }} />
        <div style={{ font: '400 13px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>ตรวจสลิปเทียบกับยอดบิล เมื่อยืนยันว่าเงินเข้าบัญชีจริง กด "ยืนยันรับชำระ" สถานะห้องจะเปลี่ยนเป็นชำระแล้วทันที</div>
      </div>
      {queue.length === 0 ? (
        <VP title="ตรวจสลิป" icon={NK.Wallet} count={0}><VEmpty title="ตรวจครบแล้ว" sub="ไม่มีสลิปรอตรวจในตอนนี้" /></VP>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(380px, 1fr))', gap: 16 }}>
          {queue.map(r => {
            const b = Store.curBill(r.id); const tot = Store.totals(b);
            return (
              <div key={r.id} style={{ background: '#fff', borderRadius: 18, boxShadow: 'var(--shadow-sm)', overflow: 'hidden' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '14px 18px', borderBottom: '1px solid var(--ink-100)' }}>
                  <div style={{ width: 40, height: 40, borderRadius: 11, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', font: '800 15px var(--font-mono)' }}>{r.no}</div>
                  <div style={{ flex: 1 }}><div style={{ font: '700 15px var(--font-display)', color: 'var(--ink-900)' }}>{r.nick}</div><div style={{ font: '500 11.5px var(--font-mono)', color: 'var(--fg-subtle)' }}>แจ้งเมื่อ {b.payment.notifiedAt}</div></div>
                  <UI.StageBadge stage="slip_review" size="sm" />
                </div>
                <div style={{ display: 'flex', gap: 14, padding: 18 }}>
                  <div style={{ width: 150, flex: 'none', borderRadius: 12, overflow: 'hidden', boxShadow: 'var(--shadow-xs)' }}><VSlip amount={tot.total} h={170} /></div>
                  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 11 }}>
                    <KVv k="ยอดบิล" v={`฿${Store.money(tot.total)}`} big />
                    <KVv k="ยอดในสลิป" v={`฿${Store.money(tot.total)}`} accent="var(--success)" />
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '600 12px var(--font-body)', color: 'var(--success)', background: 'var(--success-bg)', padding: '6px 11px', borderRadius: 999, alignSelf: 'flex-start' }}><NK.Check size={14} />ยอดตรงกัน</div>
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 10, padding: '0 18px 18px' }}>
                  <VA.Btn kind="outline" full style={{ color: 'var(--danger)' }} icon={<NK.X size={16} />} onClick={() => setReject(r.id)}>ตีกลับ</VA.Btn>
                  <VA.Btn kind="primary" full icon={<NK.Check size={16} />} onClick={() => { Store.confirmPayment(r.id); refresh(); }}>ยืนยันรับชำระ</VA.Btn>
                </div>
              </div>
            );
          })}
        </div>
      )}
      {reject && <VReject title="ตีกลับสลิป" presets={['ยอดเงินไม่ตรง', 'สลิปไม่ชัด', 'โอนผิดบัญชี', 'ยังไม่พบเงินเข้า']} onClose={() => setReject(null)} onConfirm={(reason) => { Store.rejectSlip(reject, reason); setReject(null); refresh(); }} />}
    </div>
  );
}

// =================================================================
// ROOMS & SETTINGS
// =================================================================
function Rooms({ refresh }) {
  const [w, setW] = vUseState(String(Store.rates.water));
  const [e, setE] = vUseState(String(Store.rates.elec));
  const m = Store.meta;
  const dirty = +w !== Store.rates.water || +e !== Store.rates.elec;
  return (
    <div style={{ padding: 24, maxWidth: 1040, margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
        {/* rates */}
        <VP title="อัตราค่าบริการ" icon={NK.Settings}>
          <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
            <RateField cat="water" label="ค่าน้ำประปา" unit="บาท / หน่วย" value={w} onChange={setW} />
            <RateField cat="elec" label="ค่าไฟฟ้า" unit="บาท / หน่วย" value={e} onChange={setE} />
            <VA.Btn full disabled={!dirty} style={{ opacity: dirty ? 1 : .45 }} icon={<NK.Check size={17} />} onClick={() => { Store.setRates(w, e); refresh(); }}>{dirty ? 'บันทึกอัตราใหม่' : 'บันทึกแล้ว'}</VA.Btn>
            <p style={{ margin: 0, font: '400 11.5px/1.5 var(--font-body)', color: 'var(--fg-subtle)' }}>อัตราใหม่จะใช้กับบิลที่ออกหลังจากนี้ บิลที่ออกไปแล้วใช้อัตราเดิม</p>
          </div>
        </VP>
        {/* bank */}
        <VP title="บัญชีรับชำระ" icon={NK.Qr}>
          <div style={{ padding: 20, display: 'flex', gap: 16, alignItems: 'center' }}>
            <div style={{ padding: 8, background: '#fff', borderRadius: 14, boxShadow: 'inset 0 0 0 1.5px var(--border)', flex: 'none' }}><UI.FauxQR data={m.promptpay} size={108} /></div>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <BankRow label="ธนาคาร" value={m.bankName} />
              <BankRow label="เลขบัญชี" value={m.bankAcc} mono />
              <BankRow label="พร้อมเพย์" value={m.promptpay} mono />
              <BankRow label="ชื่อบัญชี" value={m.bankHolder} />
            </div>
          </div>
        </VP>
      </div>

      {/* rooms table */}
      <VP title="ห้องพัก & ผู้เช่า" icon={NK.Building} count={Store.rooms.length}>
        <div style={{ display: 'grid', gridTemplateColumns: '70px 1fr 110px 130px 110px 130px', gap: 12, padding: '11px 20px', borderBottom: '1px solid var(--ink-100)', font: '600 11px var(--font-body)', color: 'var(--fg-subtle)', letterSpacing: '.03em', textTransform: 'uppercase' }}>
          <div>ห้อง</div><div>ผู้เช่า</div><div>ประเภท</div><div>เบอร์โทร</div><div>ค่าเช่า</div><div>สถานะเดือนนี้</div>
        </div>
        {Store.rooms.map(r => {
          const b = Store.curBill(r.id); const stage = Store.stageOf(b);
          return (
            <div key={r.id} style={{ display: 'grid', gridTemplateColumns: '70px 1fr 110px 130px 110px 130px', gap: 12, padding: '12px 20px', borderBottom: '1px solid var(--ink-100)', alignItems: 'center' }}>
              <div style={{ font: '800 14px var(--font-mono)', color: 'var(--ink-900)' }}>{r.no}</div>
              <div style={{ minWidth: 0 }}>{r.vacant ? <span style={{ font: '500 13px var(--font-body)', color: 'var(--fg-subtle)' }}>— ว่าง —</span> : <><div style={{ font: '600 13.5px var(--font-body)', color: 'var(--ink-900)' }}>{r.name}</div><div style={{ font: '500 11.5px var(--font-body)', color: 'var(--fg-muted)' }}>{r.nick}</div></>}</div>
              <div style={{ font: '500 12.5px var(--font-body)', color: 'var(--fg-muted)' }}>{r.type === 'corner' ? 'หัวมุม' : 'มาตรฐาน'}</div>
              <div style={{ font: '500 12.5px var(--font-mono)', color: 'var(--fg-muted)' }}>{r.phone || '—'}</div>
              <div style={{ font: '600 13px var(--font-mono)', color: 'var(--ink-800)' }}>฿{Store.money(r.rent)}</div>
              <div><UI.StageBadge stage={stage} size="sm" /></div>
            </div>
          );
        })}
      </VP>
    </div>
  );
}

function RateField({ cat, label, unit, value, onChange }) {
  const c = UI.CAT[cat];
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 13 }}>
      <div style={{ width: 42, height: 42, borderRadius: 12, background: c.bg, color: c.fg, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><c.Icon size={22} /></div>
      <div style={{ flex: 1 }}><div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-900)' }}>{label}</div><div style={{ font: '500 11.5px var(--font-body)', color: 'var(--fg-subtle)' }}>{unit}</div></div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 4, background: '#fff', border: '1.5px solid var(--border)', borderRadius: 12, padding: '4px 12px' }}>
        <span style={{ font: '500 13px var(--font-body)', color: 'var(--fg-subtle)' }}>฿</span>
        <input value={value} onChange={e => onChange(e.target.value.replace(/[^0-9.]/g, ''))} inputMode="decimal" style={{ width: 56, border: 'none', outline: 'none', font: '700 18px var(--font-mono)', color: 'var(--ink-900)', textAlign: 'right' }} />
      </div>
    </div>
  );
}
function BankRow({ label, value, mono }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 10 }}>
      <span style={{ font: '500 12px var(--font-body)', color: 'var(--fg-muted)', flex: 'none' }}>{label}</span>
      <span style={{ font: `600 ${mono ? 13 : 13}px ${mono ? 'var(--font-mono)' : 'var(--font-body)'}`, color: 'var(--ink-900)', textAlign: 'right' }}>{value}</span>
    </div>
  );
}
function KVv({ k, v, accent, big }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 8 }}>
      <span style={{ font: '500 12px var(--font-body)', color: 'var(--fg-muted)', flex: 'none' }}>{k}</span>
      <span style={{ font: `${big ? 700 : 600} ${big ? 18 : 13.5}px var(--font-mono)`, color: accent || 'var(--ink-900)', textAlign: 'right' }}>{v}</span>
    </div>
  );
}

window.AdminViews = { ElecEntry, WaterVerify, SlipVerify, Rooms };
