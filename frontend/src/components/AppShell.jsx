import { useState } from 'react'
import {
  Activity, Bell, BriefcaseBusiness, CalendarDays, ChartNoAxesCombined,
  ChevronDown, ChevronRight, Clock3, FileSpreadsheet, FileText, LayoutDashboard,
  LogOut, MapPinned, Menu, Settings, ShieldCheck, UsersRound, X,
} from 'lucide-react'
import umtcLogo from '../assets/umtc-logo.png'

const navigation = [
  { heading: 'OVERVIEW', items: [{ label: 'Dashboard', icon: LayoutDashboard }] },
  { heading: 'PORTFOLIO', items: [
    { label: 'Portfolio Management', icon: BriefcaseBusiness, adminOnly: true },
    { label: 'Excel Import', icon: FileSpreadsheet, adminOnly: true },
    { label: 'Agent Management', icon: UsersRound, adminOnly: true },
    { label: 'Agent By Cluster', icon: MapPinned },
    { label: 'Area Monitoring', icon: ChartNoAxesCombined },
    { label: 'No Updates Yet', icon: Clock3 },
  ] },
  { heading: 'FIELD OPERATIONS', items: [
    { label: 'Fieldwork Calendar', icon: CalendarDays },
    { label: 'Schedule Management', icon: CalendarDays },
    { label: 'Fieldwork History', icon: Activity },
  ] },
  { heading: 'REPORTS', items: [{ label: 'Reports & Analytics', icon: FileText }] },
  { heading: 'SYSTEM', items: [
    { label: 'User Management', icon: ShieldCheck, adminOnly: true },
    { label: 'Activity Logs', icon: Activity, adminOnly: true },
    { label: 'Settings', icon: Settings, adminOnly: true },
  ] },
]

function initials(name = '') {
  return name.split(/\s+/).filter(Boolean).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
}

export default function AppShell({ activePage, onNavigate, user, onLogout, children }) {
  const [collapsed, setCollapsed] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [logoutError, setLogoutError] = useState('')
  const admin = user.role === 'admin'
  const roleLabel = admin ? 'System Administrator' : 'Senior Agent'
  const groups = navigation.map((group) => ({
    ...group,
    items: group.items.filter((item) => admin || !item.adminOnly),
  })).filter((group) => group.items.length)

  function navigate(page) {
    onNavigate(page)
    setMobileOpen(false)
  }

  async function logout() {
    try {
      await onLogout()
    } catch (error) {
      setLogoutError(error.message)
      window.setTimeout(() => setLogoutError(''), 3500)
    }
  }

  return (
    <div className="min-h-screen bg-slate-50 text-ink">
      {mobileOpen && <button aria-label="Close navigation" className="fixed inset-0 z-30 bg-navy/40 lg:hidden" onClick={() => setMobileOpen(false)} />}
      <aside className={`app-sidebar fixed inset-y-0 left-0 z-40 flex flex-col bg-navy text-white transition-all duration-200 ${collapsed ? 'sidebar-collapsed' : 'sidebar-expanded'} ${mobileOpen ? 'mobile-sidebar-open' : 'mobile-sidebar-closed'}`}>
        <div className="sidebar-brand flex min-h-[82px] items-center gap-3 border-b border-white/10 px-4">
          <div className="brand-symbol grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-white"><img alt="UMTC logo" className="size-full object-cover" src={umtcLogo} /></div>
          <div className="brand-text min-w-0">
            <div className="text-[13px] font-semibold tracking-[0.12em]">UMTC SYSTEM</div>
            <div className="mt-0.5 text-[10px] leading-4 text-slate-300">Team Portfolio &amp; Field Operations</div>
          </div>
          <button aria-label="Close navigation" className="ml-auto rounded-md p-1.5 text-slate-300 hover:bg-white/10 lg:hidden" onClick={() => setMobileOpen(false)}><X size={18} /></button>
        </div>
        <div className="sidebar-nav flex-1 overflow-y-auto px-3 py-4">
          {groups.map((group) => (
            <div className="mb-3" key={group.heading}>
              <p className="nav-heading mb-1 px-3 text-[10px] font-semibold tracking-[0.13em] text-slate-400">{group.heading}</p>
              <div className="space-y-0.5">
                {group.items.map(({ label, icon: Icon }) => {
                  const active = label === activePage
                  return (
                    <button aria-current={active ? 'page' : undefined} className={`nav-item group relative flex w-full items-center gap-3 rounded-md px-3 py-1.5 text-left text-[13px] leading-4 transition-colors ${active ? 'bg-white/12 font-semibold text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white'}`} key={label} onClick={() => navigate(label)} title={collapsed ? label : undefined}>
                      {active && <span className="nav-active-mark absolute bottom-2 left-0 top-2 w-[3px] rounded-r bg-blue-400" />}
                      <Icon className="shrink-0" size={17} strokeWidth={1.8} /><span className="nav-label truncate">{label}</span>
                      {collapsed && <span className="nav-tooltip">{label}</span>}
                    </button>
                  )
                })}
              </div>
            </div>
          ))}
        </div>
      </aside>

      <div className={`app-main min-h-screen transition-[margin] duration-200 ${collapsed ? 'main-collapsed' : 'main-expanded'}`}>
        <header className="sticky top-0 z-20 flex h-[70px] items-center justify-between border-b border-line bg-white/95 px-4 backdrop-blur md:px-7">
          <div className="flex min-w-0 items-center gap-3">
            <button aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'} className="hidden rounded-md p-2 text-slate-600 transition-colors hover:bg-slate-100 hover:text-navy lg:inline-flex" onClick={() => setCollapsed((value) => !value)}><Menu size={19} /></button>
            <button aria-label="Open navigation" className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" onClick={() => setMobileOpen(true)}><Menu size={19} /></button>
            <div className="min-w-0">
              <div className="hidden items-center gap-1.5 text-[11px] text-muted sm:flex"><span>UMTC System</span><ChevronRight size={13} /><span className="truncate text-slate-600">{activePage}</span></div>
              <h1 className="truncate text-[15px] font-semibold text-ink sm:mt-0.5">{activePage}</h1>
            </div>
          </div>
          <div className="flex items-center gap-2 sm:gap-3">
            <div className="relative">
              <button aria-label="Notifications" className={`rounded-md p-2 transition-colors hover:bg-slate-100 ${notificationsOpen ? 'bg-slate-100 text-navy' : 'text-slate-600'}`} onClick={() => { setNotificationsOpen((value) => !value); setProfileOpen(false) }}><Bell size={18} strokeWidth={1.8} /></button>
              {notificationsOpen && <div className="absolute right-0 top-11 z-30 w-64 rounded-lg border border-line bg-white p-4 text-xs text-muted shadow-panel">Notifications are not configured yet.</div>}
            </div>
            <div className="relative">
              <button className="flex items-center gap-2 rounded-md p-1.5 text-left transition-colors hover:bg-slate-50" onClick={() => { setProfileOpen((value) => !value); setNotificationsOpen(false) }}>
                <span className="user-avatar grid size-8 shrink-0 place-items-center rounded-full bg-blue-50 text-[11px] font-semibold text-blue">{initials(user.name)}</span>
                <span className="hidden min-w-0 sm:block"><span className="block truncate text-xs font-semibold text-ink">{user.name}</span><span className="mt-0.5 block truncate text-[10px] text-muted">{roleLabel}</span></span>
                <ChevronDown className="hidden text-slate-400 sm:block" size={14} />
              </button>
              {profileOpen && <div className="absolute right-0 top-12 z-30 w-60 rounded-lg border border-line bg-white p-3 shadow-panel"><p className="text-xs font-semibold text-ink">{user.name}</p><p className="mt-1 text-[10px] text-muted">{user.email}</p><p className="mt-2 border-t border-line pt-2 text-[10px] font-medium text-blue">{roleLabel}</p><button className="mt-3 flex w-full items-center gap-2 rounded-md border-t border-line px-1 pt-3 text-left text-xs font-medium text-red-600 transition-colors hover:text-red-700" onClick={() => { setProfileOpen(false); logout() }}><LogOut size={15} strokeWidth={1.8} />Log out</button></div>}
            </div>
          </div>
        </header>
        <div className="page-content px-4 py-6 md:px-7 md:py-7">{children}</div>
      </div>
      {logoutError && <div className="fixed bottom-5 right-5 z-50 rounded-md bg-danger px-4 py-3 text-xs font-medium text-white shadow-panel" role="alert">{logoutError}</div>}
    </div>
  )
}
