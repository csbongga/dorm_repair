// student-app.jsx — น้องเก็บบิล: นักศึกษา — flows + root. Depends on student-parts.jsx.
const { NumberPad, MeterPhoto, LiffHeader, SubHeader, Login, Home } = window.StudentParts;
const S_UI = UI; // alias

// =================================================================
// WATER SUBMIT
// =================================================================
function WaterSubmit({ room, onBack, onDone }) {
  const b = Store.curBill(room.id);
  const rejected = b.water.status === 'reject';
  const [photo, setPhoto] = useState(false);
  const [val, setVal] = useState('');
  const [confirm, setConfirm] = useState(false);
  const prev = b.water.prev;
  const num = parseFloat(val);
  const valid = photo && val !== '' && !isNaN(num) && num >= prev;
  const units = !isNaN(num) ? Math.max(0, num - prev) : null;

  const key = (k) => {
    if (k === 'del') setVal(v => v.slice(0, -1));
    else if (k === '.') setVal(v => v.includes('.') ? v : v + '.');
    else setVal(v => (v + k).slice(0, 7));
  };

  if (confirm) {
    return (
      <div style={{ height: '100%', overflowY: 'auto' }}>
        <div style={{ minHeight: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '70px 30px', textAlign: 'center', background: 'linear-gradient(180deg,#EAF4FB,#F3F8F9 50%)' }}>
          <div style={{ width: 84, height: 84, borderRadius: '50%', background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: 'var(--shadow-md)', color: CAT.water.fg, marginBottom: 20 }}><NK.CheckCircle size={46} /></div>
          <h2 style={{ margin: '0 0 8px', font: '800 23px var(--font-display)', color: 'var(--ink-900)' }}>ส่งเลขน้ำเรียบร้อย!</h2>
          <p style={{ margin: '0 0 22px', font: '400 14px/1.6 var(--font-body)', color: 'var(--fg-muted)', textWrap: 'pretty' }}>ผู้ดูแลจะตรวจรูปเทียบกับตัวเลขที่คุณส่ง<br />หากเรียบร้อย บิลเดือนนี้จะถูกออกให้อัตโนมัติ</p>
          <div style={{ width: '100%', maxWidth: 280, background: '#fff', borderRadius: 16, padding: 16, boxShadow: 'var(--shadow-sm)', marginBottom: 24 }}>
            <Row k="เลขมิเตอร์ที่ส่ง" v={`${num} หน่วย`} />
            <Row k="ครั้งก่อน" v={`${prev} หน่วย`} />
            <div style={{ height: 1, background: 'var(--border)', margin: '8px 0' }} />
            <Row k="ใช้ไปเดือนนี้" v={`${units} หน่วย`} accent={CAT.water.fg} bold />
          </div>
          <S_UI.Btn full onClick={onDone}>กลับหน้าแรก</S_UI.Btn>
        </div>
      </div>
    );
  }

  return (
    <div style={{ height: '100%', overflowY: 'auto', background: 'var(--bg-canvas)' }}>
      <SubHeader title={rejected ? 'ส่งเลขน้ำใหม่' : 'ส่งเลขมิเตอร์น้ำ'} onBack={onBack} />
      <div style={{ padding: '14px 16px 30px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        {rejected && (
          <div style={{ display: 'flex', gap: 10, background: 'var(--danger-bg)', borderRadius: 14, padding: '12px 13px' }}>
            <NK.Alert size={19} style={{ color: 'var(--danger)', flex: 'none', marginTop: 1 }} />
            <div><div style={{ font: '700 12.5px var(--font-display)', color: 'var(--danger)', marginBottom: 2 }}>ผู้ดูแลตีกลับ</div><div style={{ font: '400 12.5px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>{b.water.reason}</div></div>
          </div>
        )}
        <div style={{ display: 'flex', gap: 8, background: CAT.water.soft, borderRadius: 14, padding: '11px 13px', alignItems: 'center' }}>
          <NK.Info size={18} style={{ color: CAT.water.fg, flex: 'none' }} />
          <div style={{ font: '400 12.5px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>ถ่ายให้เห็น<b>หน้าปัดมิเตอร์เต็มๆ</b> แล้วพิมพ์ตัวเลขที่อ่านได้ให้ตรงกับในรูป</div>
        </div>

        {/* photo */}
        <div>
          <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)', marginBottom: 8 }}>1 · รูปถ่ายหน้าปัดมิเตอร์</div>
          {photo ? (
            <div style={{ position: 'relative' }}>
              <MeterPhoto reading={val ? Math.round(num) : 1480} kind="water" h={160} />
              <button onClick={() => setPhoto(false)} style={{ position: 'absolute', top: 8, right: 8, width: 30, height: 30, borderRadius: '50%', background: 'rgba(14,42,45,.6)', color: '#fff', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', backdropFilter: 'blur(4px)' }}><NK.Refresh size={15} /></button>
            </div>
          ) : (
            <button onClick={() => setPhoto(true)} style={{ width: '100%', height: 140, borderRadius: 16, border: '2px dashed var(--border-strong)', background: '#fff', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8, color: CAT.water.fg }}>
              <NK.Camera size={30} /><span style={{ font: '600 13px var(--font-body)', color: 'var(--ink-700)' }}>แตะเพื่อถ่ายรูปมิเตอร์</span>
            </button>
          )}
        </div>

        {/* number */}
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 8 }}>
            <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)' }}>2 · เลขที่อ่านได้</div>
            <div style={{ font: '500 11.5px var(--font-mono)', color: 'var(--fg-muted)' }}>ครั้งก่อน {prev}</div>
          </div>
          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'center', gap: 8, background: '#fff', borderRadius: 16, padding: '18px', boxShadow: 'var(--shadow-xs)', border: val && !valid && val !== '' && num < prev ? '1.5px solid var(--danger)' : '1.5px solid transparent' }}>
            <span style={{ font: '700 38px var(--font-mono)', color: val ? 'var(--ink-900)' : 'var(--ink-300)', letterSpacing: '.02em' }}>{val || '0'}</span>
            <span style={{ font: '600 14px var(--font-body)', color: 'var(--fg-muted)' }}>หน่วย</span>
          </div>
          {units != null && val !== '' && (num >= prev) && <div style={{ textAlign: 'center', marginTop: 8, font: '600 13px var(--font-body)', color: CAT.water.fg }}>ใช้ไป {units} หน่วย</div>}
          {val !== '' && num < prev && <div style={{ textAlign: 'center', marginTop: 8, font: '600 12.5px var(--font-body)', color: 'var(--danger)' }}>ต้องมากกว่าหรือเท่ากับเลขครั้งก่อน ({prev})</div>}
        </div>

        <NumberPad onKey={key} />
        <S_UI.Btn full size="lg" disabled={!valid} style={{ opacity: valid ? 1 : .45 }} icon={<NK.Upload size={18} />}
          onClick={() => { if (!valid) return; Store.submitWater(room.id, num); setConfirm(true); }}>
          ส่งเลขมิเตอร์น้ำ
        </S_UI.Btn>
      </div>
    </div>
  );
}

function Row({ k, v, accent, bold }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '4px 0' }}>
      <span style={{ font: '500 13px var(--font-body)', color: 'var(--fg-muted)' }}>{k}</span>
      <span style={{ font: `${bold ? 700 : 600} 14px var(--font-mono)`, color: accent || 'var(--ink-900)' }}>{v}</span>
    </div>
  );
}

// =================================================================
// INVOICE
// =================================================================
function Invoice({ room, cycle, onBack, onPay }) {
  const b = Store.bill(cycle, room.id);
  const stage = Store.stageOf(cycle === Store.current ? b : b);
  const isPaid = b.payment.status === 'paid';
  const isReview = b.payment.status === 'review';
  const tot = Store.totals(b);
  const m = Store.meta;
  const cyMeta = Store.cycles.find(c => c.id === cycle);
  const overdue = b.overdue && !isPaid;

  return (
    <div style={{ height: '100%', overflowY: 'auto', background: 'var(--bg-canvas)' }}>
      <SubHeader title="ใบแจ้งหนี้" onBack={onBack} />
      <div style={{ padding: '14px 16px 30px' }}>
        {/* invoice card */}
        <div style={{ background: '#fff', borderRadius: 22, boxShadow: 'var(--shadow-md)', overflow: 'hidden' }}>
          <div style={{ padding: '18px 18px 14px', background: 'linear-gradient(135deg,#0E7A72,#0C625C)', color: '#fff', position: 'relative', overflow: 'hidden' }}>
            <div style={{ position: 'absolute', right: -24, top: -24, width: 110, height: 110, borderRadius: '50%', background: 'rgba(255,255,255,.08)' }} />
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', position: 'relative' }}>
              <div>
                <div style={{ font: '500 11px var(--font-body)', color: 'rgba(255,255,255,.75)' }}>{m.dormName} · ห้อง {room.no}</div>
                <div style={{ font: '800 19px var(--font-display)', marginTop: 2 }}>{cyMeta ? cyMeta.label : cycle}</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ font: '500 10px var(--font-mono)', color: 'rgba(255,255,255,.7)' }}>เลขที่</div>
                <div style={{ font: '600 12px var(--font-mono)' }}>INV-{cycle.replace('-', '')}-{room.no}</div>
              </div>
            </div>
            <div style={{ marginTop: 14, display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', position: 'relative' }}>
              <div><div style={{ font: '500 11px var(--font-body)', color: 'rgba(255,255,255,.75)' }}>ยอดชำระทั้งสิ้น</div><div style={{ font: '800 30px var(--font-mono)', letterSpacing: '-.01em' }}>฿{Store.money(tot.total)}</div></div>
              {isPaid ? <span style={{ font: '700 12px var(--font-body)', background: 'rgba(255,255,255,.2)', padding: '6px 12px', borderRadius: 999, display: 'inline-flex', gap: 5, alignItems: 'center' }}><NK.Check size={14} />ชำระแล้ว</span>
                : <span style={{ font: '700 12px var(--font-body)', background: overdue ? 'rgba(207,92,78,.9)' : 'rgba(255,255,255,.2)', padding: '6px 12px', borderRadius: 999 }}>{isReview ? 'รอตรวจสลิป' : overdue ? 'เกินกำหนด' : 'รอชำระ'}</span>}
            </div>
          </div>

          {/* breakdown */}
          <div style={{ padding: '6px 18px 4px' }}>
            <S_UI.LineRow cat="water" title="ค่าน้ำประปา" sub={`${b.water.prev} → ${b.water.curr} · ${tot.wUnits} หน่วย × ฿${b.rate.water}`} right={<S_UI.Baht n={tot.wAmt} size={15} />} />
            <div style={{ height: 1, background: 'var(--ink-100)' }} />
            <S_UI.LineRow cat="elec" title="ค่าไฟฟ้า" sub={`${b.elec.prev} → ${b.elec.curr} · ${tot.eUnits} หน่วย × ฿${b.rate.elec}`} right={<S_UI.Baht n={tot.eAmt} size={15} />} />
            <div style={{ height: 1, background: 'var(--ink-100)' }} />
            <S_UI.LineRow cat="rent" title="ค่าเช่าห้องพัก" sub={`ห้อง ${room.type === 'corner' ? 'หัวมุม' : 'มาตรฐาน'}`} right={<S_UI.Baht n={tot.rent} size={15} />} />
          </div>
          <div style={{ margin: '4px 18px 0', padding: '14px 0 16px', borderTop: '1.5px dashed var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ font: '700 15px var(--font-display)', color: 'var(--ink-900)' }}>รวมทั้งสิ้น</span>
            <S_UI.Baht n={tot.total} size={22} weight={700} color="var(--teal-700)" />
          </div>
        </div>

        {/* pay section */}
        {isPaid ? (
          <div style={{ marginTop: 14, background: 'var(--success-bg)', borderRadius: 18, padding: 16, display: 'flex', alignItems: 'center', gap: 12 }}>
            <div style={{ width: 44, height: 44, borderRadius: 13, background: '#fff', color: 'var(--success)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><NK.CheckCircle size={24} /></div>
            <div style={{ flex: 1 }}><div style={{ font: '700 14px var(--font-display)', color: 'var(--success)' }}>ชำระเรียบร้อยแล้ว</div><div style={{ font: '500 12px var(--font-mono)', color: 'var(--ink-600)' }}>รับชำระเมื่อ {b.payment.paidAt}</div></div>
          </div>
        ) : isReview ? (
          <div style={{ marginTop: 14, background: '#DCEEF9', borderRadius: 18, padding: 16, display: 'flex', alignItems: 'center', gap: 12 }}>
            <div style={{ width: 44, height: 44, borderRadius: 13, background: '#fff', color: '#2E7CB8', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}><NK.Clock size={24} /></div>
            <div style={{ flex: 1 }}><div style={{ font: '700 14px var(--font-display)', color: '#2E7CB8' }}>ส่งสลิปแล้ว รอตรวจสอบ</div><div style={{ font: '500 12px var(--font-mono)', color: 'var(--ink-600)' }}>แจ้งเมื่อ {b.payment.notifiedAt}</div></div>
          </div>
        ) : (
          <>
            {/* bank + qr */}
            <div style={{ marginTop: 14, background: '#fff', borderRadius: 18, padding: 16, boxShadow: 'var(--shadow-sm)' }}>
              <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 7 }}><NK.Qr size={17} style={{ color: 'var(--teal-600)' }} />ช่องทางชำระเงิน</div>
              <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
                <div style={{ padding: 8, background: '#fff', borderRadius: 14, boxShadow: 'inset 0 0 0 1.5px var(--border)' }}><S_UI.FauxQR data={m.promptpay + tot.total} size={104} /></div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ font: '600 11px var(--font-body)', color: 'var(--fg-muted)' }}>พร้อมเพย์ (PromptPay)</div>
                  <div style={{ font: '700 14px var(--font-mono)', color: 'var(--ink-900)', marginBottom: 8 }}>{m.promptpay}</div>
                  <div style={{ font: '600 11px var(--font-body)', color: 'var(--fg-muted)' }}>{m.bankName}</div>
                  <div style={{ font: '700 13.5px var(--font-mono)', color: 'var(--ink-900)' }}>{m.bankAcc}</div>
                  <div style={{ font: '500 11px var(--font-body)', color: 'var(--fg-subtle)', marginTop: 2 }}>{m.bankHolder}</div>
                </div>
              </div>
            </div>
            <div style={{ marginTop: 12, display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'center', font: '500 12px var(--font-body)', color: overdue ? 'var(--danger)' : 'var(--fg-muted)' }}>
              <NK.Calendar size={15} />{overdue ? `เกินกำหนดชำระ (${b.due})` : `กรุณาชำระภายใน ${b.due}`}
            </div>
            <div style={{ marginTop: 12 }}><S_UI.Btn full size="lg" icon={<NK.Upload size={19} />} onClick={onPay}>แจ้งชำระเงิน & แนบสลิป</S_UI.Btn></div>
          </>
        )}
      </div>
    </div>
  );
}

// =================================================================
// PAY (upload slip)
// =================================================================
function Pay({ room, onBack, onDone }) {
  const b = Store.curBill(room.id);
  const tot = Store.totals(b);
  const [slip, setSlip] = useState(false);
  const [done, setDone] = useState(false);

  if (done) {
    return (
      <div style={{ height: '100%', overflowY: 'auto' }}>
        <div style={{ minHeight: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '70px 30px', textAlign: 'center', background: 'linear-gradient(180deg,#E6F5F4,#F3F8F9 50%)' }}>
          <div style={{ width: 84, height: 84, borderRadius: '50%', background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: 'var(--shadow-md)', color: 'var(--teal-600)', marginBottom: 20 }}><NK.CheckCircle size={46} /></div>
          <h2 style={{ margin: '0 0 8px', font: '800 23px var(--font-display)', color: 'var(--ink-900)' }}>แจ้งชำระเรียบร้อย!</h2>
          <p style={{ margin: '0 0 24px', font: '400 14px/1.6 var(--font-body)', color: 'var(--fg-muted)', textWrap: 'pretty' }}>ผู้ดูแลจะตรวจสอบยอดเงินกับสลิปของคุณ<br />เมื่อยืนยันแล้วสถานะจะเปลี่ยนเป็น "ชำระแล้ว"</p>
          <S_UI.Btn full onClick={onDone}>กลับหน้าแรก</S_UI.Btn>
        </div>
      </div>
    );
  }

  return (
    <div style={{ height: '100%', overflowY: 'auto', background: 'var(--bg-canvas)' }}>
      <SubHeader title="แจ้งชำระเงิน" onBack={onBack} />
      <div style={{ padding: '14px 16px 30px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div style={{ background: '#fff', borderRadius: 18, padding: 16, boxShadow: 'var(--shadow-sm)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div><div style={{ font: '500 11px var(--font-body)', color: 'var(--fg-muted)' }}>ยอดที่ต้องชำระ · ห้อง {room.no}</div><S_UI.Baht n={tot.total} size={26} weight={700} /></div>
          <div style={{ textAlign: 'right', font: '500 11px var(--font-mono)', color: 'var(--fg-subtle)' }}>{Store.meta.bankName}<br />{Store.meta.bankAcc}</div>
        </div>

        <div>
          <div style={{ font: '700 13px var(--font-display)', color: 'var(--ink-800)', marginBottom: 8 }}>แนบสลิปโอนเงิน</div>
          {slip ? (
            <div style={{ position: 'relative', borderRadius: 16, overflow: 'hidden', boxShadow: 'var(--shadow-sm)' }}>
              <SlipImage amount={tot.total} />
              <button onClick={() => setSlip(false)} style={{ position: 'absolute', top: 8, right: 8, width: 30, height: 30, borderRadius: '50%', background: 'rgba(14,42,45,.6)', color: '#fff', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><NK.Refresh size={15} /></button>
            </div>
          ) : (
            <button onClick={() => setSlip(true)} style={{ width: '100%', height: 150, borderRadius: 16, border: '2px dashed var(--border-strong)', background: '#fff', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8, color: 'var(--teal-600)' }}>
              <NK.Image size={30} /><span style={{ font: '600 13px var(--font-body)', color: 'var(--ink-700)' }}>แตะเพื่อเลือกสลิปจากเครื่อง</span>
            </button>
          )}
        </div>

        <div style={{ display: 'flex', gap: 8, background: CAT.water.soft, borderRadius: 14, padding: '11px 13px', alignItems: 'center' }}>
          <NK.Info size={18} style={{ color: 'var(--teal-600)', flex: 'none' }} />
          <div style={{ font: '400 12px/1.5 var(--font-body)', color: 'var(--ink-700)' }}>ตรวจให้สลิปเห็น<b>ยอดเงินและเวลาโอน</b>ชัดเจน เพื่อให้ผู้ดูแลยืนยันได้รวดเร็ว</div>
        </div>

        <S_UI.Btn full size="lg" disabled={!slip} style={{ opacity: slip ? 1 : .45 }} icon={<NK.Check size={19} />}
          onClick={() => { if (!slip) return; Store.notifyPayment(room.id); setDone(true); }}>
          ยืนยันแจ้งชำระเงิน
        </S_UI.Btn>
      </div>
    </div>
  );
}

function SlipImage({ amount }) {
  return (
    <div style={{ height: 200, background: 'linear-gradient(160deg,#EAF1F2,#dde7e8)', position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
      <div style={{ width: 50, height: 50, borderRadius: '50%', background: '#06C755', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff' }}><NK.Check size={28} /></div>
      <div style={{ font: '700 14px var(--font-display)', color: 'var(--ink-800)' }}>โอนเงินสำเร็จ</div>
      <div style={{ font: '800 24px var(--font-mono)', color: 'var(--ink-900)' }}>฿{Store.money(amount)}.00</div>
      <div style={{ font: '500 11px var(--font-mono)', color: 'var(--fg-muted)' }}>30 พ.ค. 69 · 11:18 น.</div>
      <div style={{ position: 'absolute', bottom: 8, font: '500 10px var(--font-mono)', color: 'var(--fg-subtle)' }}>slip_payment.jpg</div>
    </div>
  );
}

// =================================================================
// HISTORY
// =================================================================
function History({ room, onBack, onOpen }) {
  const list = Store.cycles.slice().reverse().map(c => ({ c, b: Store.bill(c.id, room.id) })).filter(x => x.b);
  return (
    <div style={{ height: '100%', overflowY: 'auto', background: 'var(--bg-canvas)' }}>
      <SubHeader title="ประวัติการชำระ" onBack={onBack} />
      <div style={{ padding: '14px 16px 30px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        {list.map(({ c, b }) => {
          const stage = Store.stageOf(c.id === Store.current ? b : b);
          const tot = Store.totals(b);
          const issued = b.issued;
          return (
            <button key={c.id} onClick={() => issued && onOpen(c.id)} style={{ display: 'flex', alignItems: 'center', gap: 13, background: '#fff', border: 'none', borderRadius: 16, padding: '14px 15px', boxShadow: 'var(--shadow-xs)', cursor: issued ? 'pointer' : 'default', textAlign: 'left', opacity: issued ? 1 : .6 }}>
              <div style={{ width: 44, height: 44, borderRadius: 12, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', flex: 'none' }}>
                <span style={{ font: '700 14px var(--font-display)' }}>{c.short}</span>
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ font: '700 14.5px var(--font-display)', color: 'var(--ink-900)' }}>{c.label}</div>
                <div style={{ marginTop: 4 }}><S_UI.StageBadge stage={stage} size="sm" /></div>
              </div>
              <div style={{ textAlign: 'right' }}>
                {issued ? <S_UI.Baht n={tot.total} size={15} /> : <span style={{ font: '500 12px var(--font-body)', color: 'var(--fg-subtle)' }}>ยังไม่ออกบิล</span>}
                {issued && <div style={{ marginTop: 2 }}><NK.Chevron size={15} style={{ color: 'var(--ink-300)' }} /></div>}
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}

// =================================================================
// BOTTOM NAV
// =================================================================
function BottomNav({ active, onNav, hasBill }) {
  const items = [{ k: 'home', icon: NK.Home, label: 'หน้าแรก' }, { k: 'invoice', icon: NK.Receipt, label: 'บิลของฉัน' }, { k: 'history', icon: NK.Clock, label: 'ประวัติ' }];
  return (
    <div style={{ display: 'flex', borderTop: '1px solid var(--border)', background: 'rgba(255,255,255,.92)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)', padding: '6px 8px 24px' }}>
      {items.map(it => {
        const on = active === it.k;
        return (
          <button key={it.k} onClick={() => onNav(it.k)} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3, background: 'none', border: 'none', cursor: 'pointer', padding: '6px 0', color: on ? 'var(--teal-600)' : 'var(--ink-400)' }}>
            <it.icon size={22} sw={on ? 2.4 : 2} /><span style={{ font: `${on ? 700 : 500} 10.5px var(--font-body)` }}>{it.label}</span>
          </button>
        );
      })}
    </div>
  );
}

// =================================================================
// ROOT
// =================================================================
function StudentApp() {
  const [, force] = useState(0);
  useEffect(() => Store.subscribe(() => force(x => x + 1)), []);
  const [roomId, setRoomId] = useState(null);
  const [screen, setScreen] = useState('home');
  const [cycle, setCycle] = useState(Store.current);
  const room = roomId ? Store.roomById(roomId) : null;

  const go = (s, cy) => { if (cy) setCycle(cy); else setCycle(Store.current); setScreen(s); };

  let body, showNav = false, navActive = screen;
  if (!room) {
    body = <Login onPick={(id) => { setRoomId(id); setScreen('home'); }} />;
  } else if (screen === 'water') {
    body = <WaterSubmit room={room} onBack={() => setScreen('home')} onDone={() => setScreen('home')} />;
  } else if (screen === 'invoice') {
    const b = Store.bill(cycle, room.id);
    if (!b || !b.issued) { body = <Home room={room} onGo={go} />; showNav = true; navActive = 'home'; }
    else { body = <Invoice room={room} cycle={cycle} onBack={() => setScreen('home')} onPay={() => setScreen('pay')} />; }
  } else if (screen === 'pay') {
    body = <Pay room={room} onBack={() => setScreen('invoice')} onDone={() => setScreen('home')} />;
  } else if (screen === 'history') {
    body = <History room={room} onBack={() => setScreen('home')} onOpen={(cy) => go('invoice', cy)} />;
    showNav = true;
  } else {
    body = <Home room={room} onGo={go} />;
    showNav = true; navActive = 'home';
  }

  const b = room ? Store.curBill(room.id) : null;
  const bellCount = room ? ([ 'water_wait', 'water_reject', 'unpaid', 'overdue' ].includes(Store.stageOf(b)) ? 1 : 0) : 0;

  return (
    <IOSDevice>
      <div style={{ height: '100%', display: 'flex', flexDirection: 'column', background: 'var(--bg-canvas)' }}>
        {room && ['home', 'history'].includes(screen) && (
          <LiffHeader room={room} bellCount={bellCount}
            onSwitch={() => { setRoomId(null); setScreen('home'); }}
            onBell={() => { const s = Store.stageOf(b); if (['unpaid', 'overdue'].includes(s)) go('invoice'); else if (['water_wait', 'water_reject'].includes(s)) setScreen('water'); }} />
        )}
        <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>{body}</div>
        {showNav && room && <BottomNav active={navActive} hasBill={b && b.issued} onNav={(k) => { if (k === 'invoice') go('invoice'); else setScreen(k); }} />}
      </div>
    </IOSDevice>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<StudentApp />);
