// icons.jsx — Lucide-style 2px line icons for น้องเก็บบิล. window.NK
const NKIc = ({ d, size = 20, sw = 2, fill = 'none', children, style }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke="currentColor"
       strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round" style={style}>
    {d ? <path d={d} /> : children}
  </svg>
);

const NK = {
  Drop:    (p) => <NKIc {...p} d="M12 2.7S5.5 9.4 5.5 14.2a6.5 6.5 0 0 0 13 0C18.5 9.4 12 2.7 12 2.7Z" />,
  Bolt:    (p) => <NKIc {...p} d="M13 2 4.5 13.2a.6.6 0 0 0 .48.95H11l-1 8 8.5-11.2a.6.6 0 0 0-.48-.95H12l1-8Z" />,
  Receipt: (p) => <NKIc {...p}><path d="M5 3v18l2-1.4 2 1.4 2-1.4 2 1.4 2-1.4 2 1.4V3l-2 1.4L13.5 3l-2 1.4L9.5 3 7.5 4.4 5 3Z" /><path d="M9 8h6M9 12h6M9 16h3" /></NKIc>,
  Door:    (p) => <NKIc {...p}><path d="M4 21h16" /><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17" /><circle cx="14.5" cy="12" r="1" fill="currentColor" stroke="none" /></NKIc>,
  Building:(p) => <NKIc {...p}><rect x="4" y="3" width="16" height="18" rx="1.5" /><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3" /></NKIc>,
  Grid:    (p) => <NKIc {...p}><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></NKIc>,
  Gauge:   (p) => <NKIc {...p}><path d="M12 14 16 9" /><path d="M3.5 16a9 9 0 1 1 17 0" /><circle cx="12" cy="14" r="1.4" fill="currentColor" stroke="none" /></NKIc>,
  Camera:  (p) => <NKIc {...p}><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z" /><circle cx="12" cy="13" r="3.5" /></NKIc>,
  Upload:  (p) => <NKIc {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M12 3v13M7 8l5-5 5 5" /></NKIc>,
  Image:   (p) => <NKIc {...p}><rect x="3" y="3" width="18" height="18" rx="3" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></NKIc>,
  Check:   (p) => <NKIc {...p} d="M20 6 9 17l-5-5" />,
  CheckCircle:(p) => <NKIc {...p}><circle cx="12" cy="12" r="9" /><path d="m8.5 12 2.5 2.5L16 9" /></NKIc>,
  X:       (p) => <NKIc {...p} d="M18 6 6 18M6 6l12 12" />,
  XCircle: (p) => <NKIc {...p}><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></NKIc>,
  Back:    (p) => <NKIc {...p} d="m15 18-6-6 6-6" />,
  Arrow:   (p) => <NKIc {...p} d="M5 12h14M13 6l6 6-6 6" />,
  ArrowDown:(p) => <NKIc {...p} d="M12 5v14M6 13l6 6 6-6" />,
  Chevron: (p) => <NKIc {...p} d="m9 6 6 6-6 6" />,
  More:    (p) => <NKIc {...p}><circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/></NKIc>,
  Bell:    (p) => <NKIc {...p}><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.7 21a2 2 0 0 1-3.4 0" /></NKIc>,
  Clock:   (p) => <NKIc {...p}><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></NKIc>,
  Calendar:(p) => <NKIc {...p}><rect x="3" y="4.5" width="18" height="16" rx="2.5" /><path d="M3 9.5h18M8 2.5v4M16 2.5v4" /></NKIc>,
  Home:    (p) => <NKIc {...p}><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5" /><path d="M9.5 21v-6h5v6" /></NKIc>,
  Wallet:  (p) => <NKIc {...p}><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a1 1 0 0 1 1 1v1.5" /><path d="M3 7.5V18a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3M3 7.5h16a1 1 0 0 1 1 1V12" /><circle cx="16.5" cy="13" r="1.3" fill="currentColor" stroke="none" /></NKIc>,
  Qr:      (p) => <NKIc {...p}><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><path d="M14 14h3v3M21 14v.01M14 21h3M21 17v4M17.5 17.5v.01" /></NKIc>,
  User:    (p) => <NKIc {...p}><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></NKIc>,
  Users:   (p) => <NKIc {...p}><circle cx="9" cy="8" r="3.5" /><path d="M2.5 21a6.5 6.5 0 0 1 13 0" /><path d="M16 5.2a3.5 3.5 0 0 1 0 6.6M17.5 21a6.5 6.5 0 0 0-3-5.5" /></NKIc>,
  Phone:   (p) => <NKIc {...p} d="M5 3h3l2 5-2.5 1.5a12 12 0 0 0 5 5L19 14l2 5v3a1 1 0 0 1-1.1 1A17 17 0 0 1 4 6 1 1 0 0 1 5 3Z" />,
  Settings:(p) => <NKIc {...p}><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" /></NKIc>,
  Edit:    (p) => <NKIc {...p}><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></NKIc>,
  Plus:    (p) => <NKIc {...p} d="M12 5v14M5 12h14" />,
  Search:  (p) => <NKIc {...p}><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></NKIc>,
  Filter:  (p) => <NKIc {...p} d="M3 5h18l-7 8v6l-4 2v-8L3 5Z" />,
  Info:    (p) => <NKIc {...p}><circle cx="12" cy="12" r="9" /><path d="M12 16v-4M12 8h.01" /></NKIc>,
  Alert:   (p) => <NKIc {...p}><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></NKIc>,
  Sparkle: (p) => <NKIc {...p} fill="currentColor" sw={1} d="M12 2.5l1.6 6.3 6.3 1.7-6.3 1.6L12 18.5l-1.6-6.4-6.3-1.6 6.3-1.7Z" />,
  List:    (p) => <NKIc {...p} d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" />,
  Logout:  (p) => <NKIc {...p}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></NKIc>,
  Refresh: (p) => <NKIc {...p}><path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5" /></NKIc>,
  Eye:     (p) => <NKIc {...p}><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></NKIc>,
  Copy:    (p) => <NKIc {...p}><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></NKIc>,
  Line:    (p) => <NKIc {...p} fill="currentColor" sw={0}><path d="M12 3C6.8 3 2.5 6.4 2.5 10.6c0 3.8 3.4 7 8 7.6.3.07.74.22.85.5.1.26.06.66.03.92l-.14.83c-.04.25-.2.97.85.53s5.64-3.32 7.7-5.69c1.42-1.56 2.1-3.14 2.1-4.69C21.5 6.4 17.2 3 12 3Z" /></NKIc>,
  Layers:  (p) => <NKIc {...p}><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 13 9 5 9-5M3 17l9 5 9-5" /></NKIc>,
  Droplets:(p) => <NKIc {...p}><path d="M7 16.5a4 4 0 1 0 8 0c0-2.2-4-6.5-4-6.5s-4 4.3-4 6.5Z" /><path d="M12.5 4.5S15 7 15 8.5a2 2 0 0 1-3.2 1.6" /></NKIc>,
};

window.NK = NK;
