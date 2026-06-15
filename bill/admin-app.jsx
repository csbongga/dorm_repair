// admin-app.jsx — น้องเก็บบิล: ผู้ดูแล — root shell.
const { computeStats, Sidebar, TopBar } = window.AdminShell;
const { Dashboard, RoomDrawer } = window.AdminDash;
const { ElecEntry, WaterVerify, SlipVerify, Rooms } = window.AdminViews;

const TITLES = {
  dashboard: { t: 'ภาพรวมหอพัก', s: Store.meta.dormName },
  water: { t: 'ตรวจสอบค่าน้ำ', s: 'เทียบรูปถ่ายมิเตอร์กับตัวเลขที่ผู้เช่าส่ง' },
  elec: { t: 'กรอกเลขมิเตอร์ไฟ', s: 'บันทึกเลขไฟแต่ละห้อง · ระบบคำนวณยอดให้อัตโนมัติ' },
  slip: { t: 'ตรวจสลิป & รับชำระ', s: 'ยืนยันยอดเงินที่โอนเข้าบัญชี' },
  rooms: { t: 'ห้องพัก & ตั้งค่า', s: 'อัตราค่าบริการ บัญชีรับเงิน และข้อมูลผู้เช่า' },
};

function AdminApp() {
  const [, force] = React.useState(0);
  const refresh = () => force(x => x + 1);
  React.useEffect(() => Store.subscribe(refresh), []);
  const [view, setView] = React.useState('dashboard');
  const [drawer, setDrawer] = React.useState(null);
  const stats = computeStats();
  const ti = TITLES[view];

  let body;
  if (view === 'dashboard') body = <Dashboard stats={stats} setView={setView} onOpenRoom={setDrawer} />;
  else if (view === 'water') body = <WaterVerify refresh={refresh} onOpenRoom={setDrawer} />;
  else if (view === 'elec') body = <ElecEntry refresh={refresh} />;
  else if (view === 'slip') body = <SlipVerify refresh={refresh} />;
  else if (view === 'rooms') body = <Rooms refresh={refresh} />;

  return (
    <div style={{ display: 'flex', height: '100vh', width: '100%', background: 'var(--bg-canvas)', overflow: 'hidden' }}>
      <Sidebar view={view} setView={(v) => { setView(v); setDrawer(null); }} stats={stats} />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, height: '100%' }}>
        <TopBar title={ti.t} sub={ti.s} />
        <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>{body}</div>
      </div>
      {drawer && <RoomDrawer roomId={drawer} setView={setView} onClose={() => setDrawer(null)} />}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<AdminApp />);
