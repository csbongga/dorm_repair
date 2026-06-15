/* ============================================================
   น้องเก็บบิล — shared store (plain JS, window.Store)
   Single source of truth for both surfaces (student + admin).
   Persists to localStorage and live-syncs across iframes/tabs
   via the 'storage' event. Load BEFORE the babel app scripts.
   ============================================================ */
(function () {
  const KEY = 'nkb_state_v3';
  const NOW = new Date('2026-05-30T11:20:00');

  // ---- Cycles (Thai Buddhist year 2569 = 2026) ----------------
  const CYCLES = [
    { id: '2569-03', label: 'มีนาคม 2569', short: 'มี.ค.', due: '12 เม.ย. 2569', duePast: true },
    { id: '2569-04', label: 'เมษายน 2569', short: 'เม.ย.', due: '12 พ.ค. 2569', duePast: true },
    { id: '2569-05', label: 'พฤษภาคม 2569', short: 'พ.ค.', due: '12 มิ.ย. 2569', duePast: false },
  ];
  const CURRENT = '2569-05';

  // ---- Rooms + residents -------------------------------------
  // stage drives how the CURRENT (May) cycle is seeded.
  const SEED = [
    { no: '101', floor: 1, type: 'corner', rent: 3800, name: 'ณัฐนรี ศรีสุข',     nick: 'มายด์',  phone: '081-234-5601', stage: 'paid' },
    { no: '102', floor: 1, type: 'std',    rent: 3200, name: 'พงศกร ทองดี',       nick: 'ฟร้องค์', phone: '089-552-1102', stage: 'unpaid' },
    { no: '103', floor: 1, type: 'std',    rent: 3200, name: 'ธนภัทร อยู่เย็น',    nick: 'เปรม',   phone: '062-880-7103', stage: 'water_review' },
    { no: '104', floor: 1, type: 'std',    rent: 3200, vacant: true },
    { no: '201', floor: 2, type: 'std',    rent: 3200, name: 'ชนิกานต์ พูนผล',     nick: 'จูน',    phone: '094-110-2201', stage: 'slip_review' },
    { no: '202', floor: 2, type: 'corner', rent: 3800, name: 'กิตติพศ วารินทร์',   nick: 'ต้นน้ำ', phone: '086-771-3202', stage: 'paid' },
    { no: '203', floor: 2, type: 'std',    rent: 3200, name: 'ศุภวิชญ์ มากมี',     nick: 'บีม',    phone: '090-445-6203', stage: 'water_wait', arrears: true },
    { no: '204', floor: 2, type: 'std',    rent: 3200, name: 'ปวีณ์ธิดา แก้วใส',   nick: 'อาย',    phone: '084-223-7204', stage: 'water_wait' },
    { no: '301', floor: 3, type: 'std',    rent: 3300, name: 'กันตพงศ์ ใจดี',      nick: 'กันต์',  phone: '098-332-1301', stage: 'water_review' },
    { no: '302', floor: 3, type: 'corner', rent: 3900, name: 'พิมพ์ลภัส วงศ์งาม',  nick: 'พลอย',   phone: '081-909-2302', stage: 'slip_review' },
    { no: '303', floor: 3, type: 'std',    rent: 3300, name: 'ภูมิรพี สถาพร',      nick: 'ภูมิ',   phone: '063-118-4303', stage: 'elec_wait' },
    { no: '304', floor: 3, type: 'std',    rent: 3300, vacant: true },
    { no: '401', floor: 4, type: 'corner', rent: 3900, name: 'ณัฐธยาน์ ผ่องใส',    nick: 'เนย',    phone: '087-654-1401', stage: 'unpaid' },
    { no: '402', floor: 4, type: 'std',    rent: 3300, name: 'จิรายุ ข้าวหอม',     nick: 'ข้าวฟ่าง', phone: '092-501-6402', stage: 'elec_wait' },
    { no: '403', floor: 4, type: 'std',    rent: 3300, name: 'ฟ้าใส เมฆขาว',       nick: 'ฟ้า',    phone: '085-247-8403', stage: 'water_reject' },
    { no: '404', floor: 4, type: 'std',    rent: 3300, name: 'นภัสกร ชลธี',        nick: 'น้ำ',    phone: '061-770-9404', stage: 'water_wait' },
  ];

  const RATES = { water: 18, elec: 8 }; // บาท / หน่วย
  const META = {
    dormName: 'หอพักเปี่ยมสุข',
    addr: '88/9 ถ.สุขุมวิท ต.ในเมือง อ.เมือง',
    bankName: 'ธนาคารกสิกรไทย',
    bankAcc: '042-8-83910-7',
    bankHolder: 'น.ส. เปี่ยมสุข ใจดี',
    promptpay: '0858831907',
    readWindow: 'วันที่ 26–30 ของทุกเดือน',
    dueText: 'ชำระภายในวันที่ 12 ของเดือนถัดไป',
  };

  // ---- deterministic meter generation ------------------------
  function build() {
    const rooms = [];
    const bills = {}; // `${cycle}:${no}` -> bill

    SEED.forEach((s, idx) => {
      const room = {
        id: s.no, no: s.no, floor: s.floor, type: s.type,
        rent: s.rent || 3200, vacant: !!s.vacant,
        name: s.name || null, nick: s.nick || null, phone: s.phone || null,
      };
      rooms.push(room);
      if (s.vacant) return;

      // baselines
      let w = 90 + idx * 6;          // water meter start (Mar prev)
      let e = 1240 + idx * 58;       // elec meter start (Mar prev)
      const wUse = [5, 6, 7, 4, 8, 6, 5, 9, 7, 4, 6, 8, 5, 7, 6, 9]; // per-room monthly water units
      const eUse = [78, 96, 132, 64, 110, 88, 72, 145, 103, 60, 90, 124, 81, 117, 95, 138];
      const wu = wUse[idx % wUse.length];
      const eu = eUse[idx % eUse.length];

      CYCLES.forEach((cy, ci) => {
        const wPrev = w, ePrev = e;
        const monthWobble = (ci === 0 ? -1 : ci === 2 ? 1 : 0);
        const wThis = Math.max(2, wu + (ci === 2 ? 0 : monthWobble));
        const eThis = Math.max(20, eu + monthWobble * 9);
        const wCurr = wPrev + wThis;
        const eCurr = ePrev + eThis;

        const isCurrent = cy.id === CURRENT;
        const bill = {
          cycle: cy.id, roomId: room.id, rent: room.rent,
          rate: { ...RATES },
          due: cy.due, dueLabel: cy.label,
          water: { prev: wPrev, curr: wCurr, photo: true, status: 'verified', reason: null, at: stamp(cy, 27) },
          elec:  { prev: ePrev, curr: eCurr, entered: true, at: stamp(cy, 1, true) },
          issued: true,
          payment: { status: 'paid', slip: true, notifiedAt: stamp(cy, 6, true), paidAt: stamp(cy, 7, true), reason: null },
          overdue: false,
        };

        if (isCurrent) applyStage(bill, s, room);
        else if (ci === 1 && s.arrears) {
          // previous-month unpaid -> overdue carry-over
          bill.payment = { status: 'none', slip: false, notifiedAt: null, paidAt: null, reason: null };
          bill.overdue = true;
        }

        bills[cy.id + ':' + room.id] = bill;
        w = wCurr; e = eCurr;
      });
    });

    return { rooms, bills, rates: { ...RATES }, meta: { ...META }, current: CURRENT, cycles: CYCLES };
  }

  // turn a stage label into the right set of flags for the current cycle
  function applyStage(b, s, room) {
    const wPrev = b.water.prev, wCurr = b.water.curr;
    const reset = (water, payment, issued) => {
      b.water = { ...b.water, ...water };
      b.payment = payment;
      b.issued = issued;
      b.overdue = false;
    };
    const fresh = { status: 'none', slip: false, notifiedAt: null, paidAt: null, reason: null };
    switch (s.stage) {
      case 'water_wait':
        reset({ curr: null, photo: false, status: 'wait', at: null }, { ...fresh }, false);
        b.elec.entered = false; b.elec.curr = null; break;
      case 'water_review':
        reset({ status: 'review', at: stamp(b, 28) }, { ...fresh }, false);
        b.elec.entered = false; b.elec.curr = null; break;
      case 'water_reject':
        reset({ status: 'reject', at: stamp(b, 28), reason: 'รูปเบลอ อ่านตัวเลขไม่ชัด รบกวนถ่ายใหม่ให้เห็นหน้าปัดเต็มๆ ค่ะ' }, { ...fresh }, false);
        b.elec.entered = false; b.elec.curr = null; break;
      case 'elec_wait':
        reset({ status: 'verified', at: stamp(b, 28) }, { ...fresh }, false);
        b.elec.entered = false; b.elec.curr = null; break;
      case 'unpaid':
        reset({ status: 'verified', at: stamp(b, 28) }, { ...fresh }, true); break;
      case 'slip_review':
        reset({ status: 'verified', at: stamp(b, 28) }, { status: 'review', slip: true, notifiedAt: stamp(b, 30, true), paidAt: null, reason: null }, true); break;
      case 'paid':
        reset({ status: 'verified', at: stamp(b, 28) }, { status: 'paid', slip: true, notifiedAt: stamp(b, 29, true), paidAt: stamp(b, 30, true), reason: null }, true); break;
      default: break;
    }
  }

  function stamp(cyOrBill, day, withTime) {
    const month = 'พ.ค.';
    const t = withTime ? ' ' + (8 + (day % 9)) + ':' + String((day * 7) % 60).padStart(2, '0') : '';
    return day + ' ' + month + ' 69' + t;
  }

  // ---- stage derivation + meta -------------------------------
  const STAGES = {
    water_wait:   { label: 'ค้างส่งเลขน้ำ',     short: 'รอเลขน้ำ',  tone: 'danger',  cat: 'water', who: 'student' },
    water_review: { label: 'รอตรวจค่าน้ำ',      short: 'ตรวจน้ำ',   tone: 'warning', cat: 'water', who: 'admin' },
    water_reject: { label: 'ตีกลับ–รอส่งใหม่',  short: 'ตีกลับ',    tone: 'danger',  cat: 'water', who: 'student' },
    elec_wait:    { label: 'รอกรอกค่าไฟ',       short: 'กรอกค่าไฟ', tone: 'warning', cat: 'elec',  who: 'admin' },
    unpaid:       { label: 'รอชำระเงิน',        short: 'รอชำระ',    tone: 'info',    cat: 'pay',   who: 'student' },
    overdue:      { label: 'ค้างชำระ',          short: 'ค้างชำระ',  tone: 'danger',  cat: 'pay',   who: 'student' },
    slip_review:  { label: 'รอตรวจสลิป',        short: 'ตรวจสลิป',  tone: 'warning', cat: 'pay',   who: 'admin' },
    paid:         { label: 'ชำระแล้ว',          short: 'ชำระแล้ว',  tone: 'success', cat: 'pay',   who: '-' },
    vacant:       { label: 'ห้องว่าง',          short: 'ว่าง',      tone: 'muted',   cat: '-',     who: '-' },
  };

  function roomById(state, id) { return state.rooms.find(r => r.id === id); }

  function stageOf(state, bill) {
    if (!bill) return 'vacant';
    const r = roomById(state, bill.roomId);
    if (r && r.vacant) return 'vacant';
    if (bill.payment.status === 'paid') return 'paid';
    if (bill.payment.status === 'review') return 'slip_review';
    if (bill.issued) return bill.overdue ? 'overdue' : 'unpaid';
    if (bill.water.status === 'reject') return 'water_reject';
    if (bill.water.status === 'review') return 'water_review';
    if (bill.water.status === 'wait') return 'water_wait';
    return 'elec_wait';
  }

  function totals(bill) {
    const wUnits = bill.water.curr != null ? bill.water.curr - bill.water.prev : null;
    const eUnits = bill.elec.curr != null ? bill.elec.curr - bill.elec.prev : null;
    const wAmt = wUnits != null ? wUnits * bill.rate.water : null;
    const eAmt = eUnits != null ? eUnits * bill.rate.elec : null;
    const total = (wAmt || 0) + (eAmt || 0) + bill.rent;
    return { wUnits, eUnits, wAmt, eAmt, rent: bill.rent, total };
  }

  // ---- persistence + pub/sub ---------------------------------
  let state = load();
  const subs = new Set();

  function load() {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    return build();
  }
  function save(silent) {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
    if (!silent) emit();
  }
  function emit() { subs.forEach(fn => { try { fn(state); } catch (e) {} }); }

  window.addEventListener('storage', (e) => {
    if (e.key === KEY && e.newValue) {
      try { state = JSON.parse(e.newValue); emit(); } catch (err) {}
    }
  });

  function bill(cycle, roomId) { return state.bills[(cycle || state.current) + ':' + roomId]; }
  function curBill(roomId) { return bill(state.current, roomId); }
  function issueIfReady(b) {
    if (b.water.status === 'verified' && b.elec.entered && !b.issued) {
      b.issued = true; b.overdue = false;
    }
  }

  // ---- mutations ---------------------------------------------
  const M = {
    submitWater(roomId, curr, opts) {
      const b = curBill(roomId);
      b.water.curr = curr; b.water.photo = true; b.water.status = 'review';
      b.water.reason = null; b.water.at = (opts && opts.at) || nowStamp();
      save();
    },
    verifyWater(roomId) {
      const b = curBill(roomId);
      b.water.status = 'verified'; b.water.reason = null;
      issueIfReady(b); save();
    },
    rejectWater(roomId, reason) {
      const b = curBill(roomId);
      b.water.status = 'reject'; b.water.reason = reason || 'ตัวเลข/รูปไม่ชัดเจน รบกวนส่งใหม่';
      b.water.curr = null; b.water.photo = false; save();
    },
    enterElec(roomId, curr) {
      const b = curBill(roomId);
      b.elec.curr = curr; b.elec.entered = true; b.elec.at = nowStamp();
      issueIfReady(b); save();
    },
    notifyPayment(roomId) {
      const b = curBill(roomId);
      b.payment.status = 'review'; b.payment.slip = true;
      b.payment.notifiedAt = nowStamp(); save();
    },
    confirmPayment(roomId) {
      const b = curBill(roomId);
      b.payment.status = 'paid'; b.payment.paidAt = nowStamp();
      b.overdue = false; save();
    },
    rejectSlip(roomId, reason) {
      const b = curBill(roomId);
      b.payment.status = 'none'; b.payment.slip = false;
      b.payment.reason = reason || 'ยอดเงินไม่ตรง'; save();
    },
    setRates(water, elec) {
      state.rates = { water: +water, elec: +elec }; save();
    },
    resetDemo() { state = build(); save(); },
  };

  function nowStamp() {
    return '30 พ.ค. 69 ' + NOW.getHours() + ':' + String(NOW.getMinutes()).padStart(2, '0');
  }

  // ---- public API --------------------------------------------
  window.Store = {
    NOW,
    get state() { return state; },
    get rooms() { return state.rooms; },
    get rates() { return state.rates; },
    get meta() { return state.meta; },
    get current() { return state.current; },
    get cycles() { return state.cycles; },
    STAGES,
    roomById: (id) => roomById(state, id),
    bill, curBill,
    stageOf: (b) => stageOf(state, b),
    stageMeta: (b) => STAGES[stageOf(state, b)],
    totals,
    occupied: () => state.rooms.filter(r => !r.vacant),
    subscribe(fn) { subs.add(fn); return () => subs.delete(fn); },
    ...M,
    fmt: (n) => (n == null ? '–' : Number(n).toLocaleString('en-US')),
    money: (n) => (n == null ? '–' : Number(n).toLocaleString('en-US', { minimumFractionDigits: 0 })),
  };
})();
