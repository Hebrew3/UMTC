import { lazy, Suspense, useEffect, useState } from 'react'
import { ArrowRight, Eye, EyeOff, LoaderCircle, LockKeyhole, Mail, ShieldCheck } from 'lucide-react'
import AppShell from './components/AppShell'
import { api } from './lib/api'
import umtcLogo from './assets/umtc-logo.png'

const Dashboard = lazy(() => import('./components/Dashboard'))
const OperationalPage = lazy(() => import('./components/OperationalPage'))

function Login({ onLogin, authError }) {
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [email, setEmail] = useState(() => {
    try {
      return window.localStorage.getItem('umtc-remembered-email') || ''
    } catch {
      return ''
    }
  })

  async function submit(event) {
    event.preventDefault()
    setError('')
    setBusy(true)
    const form = new FormData(event.currentTarget)
    const credentials = { email: form.get('email'), password: form.get('password') }
    const rememberEmail = form.get('remember') === 'on'
    try {
      await onLogin(credentials)
      try {
        if (rememberEmail) window.localStorage.setItem('umtc-remembered-email', credentials.email)
        else window.localStorage.removeItem('umtc-remembered-email')
      } catch {
        // The session remains valid if this browser blocks local storage.
      }
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }

  return <main className="login-page">
    <div aria-hidden="true" className="login-backdrop">
      <div className="login-orb login-orb-one" />
      <div className="login-orb login-orb-two" />
      <div className="login-orb login-orb-three" />
      <svg className="login-network" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
        <path d="M0 245 225 180 410 310 620 120 835 270 1050 110 1280 245 1440 180M80 700 300 560 520 720 760 535 960 690 1175 520 1390 680M225 180 300 560M410 310 520 720M620 120 760 535M835 270 960 690M1050 110 1175 520M1280 245 1390 680" />
        <g><circle cx="225" cy="180" r="3" /><circle cx="410" cy="310" r="3" /><circle cx="620" cy="120" r="3" /><circle cx="835" cy="270" r="3" /><circle cx="1050" cy="110" r="3" /><circle cx="1280" cy="245" r="3" /><circle cx="300" cy="560" r="3" /><circle cx="520" cy="720" r="3" /><circle cx="760" cy="535" r="3" /><circle cx="960" cy="690" r="3" /><circle cx="1175" cy="520" r="3" /><circle cx="1390" cy="680" r="3" /></g>
      </svg>
      <span className="login-particle login-particle-one" />
      <span className="login-particle login-particle-two" />
      <span className="login-particle login-particle-three" />
      <span className="login-particle login-particle-four" />
    </div>
    <section aria-labelledby="login-title" className="login-card">
      <header className="login-brand">
        <span className="login-logo-wrap"><img alt="UMTC logo" className="login-logo" src={umtcLogo} /></span>
        <span className="login-brand-name">UMTC SYSTEM</span>
        <span className="login-brand-caption">Team Portfolio &amp; Field Operations</span>
      </header>

      <div className="login-heading">
        <span className="login-step-label"><span className="login-live-dot" /> OPERATIONS PORTAL</span>
        <h1 id="login-title">Welcome Back!</h1>
        <p>Sign in to access your workspace.</p>
      </div>

      {(authError || error) && <p className="login-error" role="alert">{error || authError}</p>}

      <form className="login-form" onSubmit={submit}>
        <label className="login-label" htmlFor="login-email">Work Email</label>
        <span className="login-input-wrap"><Mail aria-hidden="true" className="login-input-icon" size={18} /><input autoComplete="username" className="login-input" id="login-email" name="email" onChange={(event) => setEmail(event.target.value)} placeholder="name@company.com" required type="email" value={email} /></span>

        <label className="login-label" htmlFor="login-password">Password</label>
        <span className="login-input-wrap"><LockKeyhole aria-hidden="true" className="login-input-icon" size={18} /><input autoComplete="current-password" className="login-input login-password-input" id="login-password" name="password" placeholder="Enter your password" required type={showPassword ? 'text' : 'password'} /><button aria-label={showPassword ? 'Hide password' : 'Show password'} className="login-password-toggle" onClick={() => setShowPassword((visible) => !visible)} type="button">{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></span>

        <label className="login-remember"><input defaultChecked={Boolean(email)} name="remember" type="checkbox" /><span className="login-checkbox" aria-hidden="true" /><span>Remember Me</span></label>

        <button className="login-submit" disabled={busy} type="submit"><span>{busy ? 'Signing in…' : 'Sign In'}</span>{busy ? <LoaderCircle aria-hidden="true" className="login-spinner" size={17} /> : <ArrowRight aria-hidden="true" size={17} />}</button>
      </form>

      <footer className="login-footer">
        <div className="login-security-note"><ShieldCheck aria-hidden="true" size={16} /><span>Secure access for authorized team members.</span></div>
        <div className="login-footer-meta"><span>UMTC Team Portfolio &amp; Field Operations</span><span>Authorized access only.</span></div>
      </footer>
    </section>
  </main>
}

function App() {
  const [user, setUser] = useState(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [authError, setAuthError] = useState('')
  const [activePage, setActivePage] = useState('Dashboard')
  const [dashboard, setDashboard] = useState(null)
  const [dashboardError, setDashboardError] = useState('')

  useEffect(() => {
    let active = true
    api.get('/auth/me')
      .then((result) => { if (active) setUser(result.user) })
      .catch((error) => {
        if (active && error.status !== 401) setAuthError(error.message)
      })
      .finally(() => { if (active) setAuthLoading(false) })
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (!user || activePage !== 'Dashboard') return undefined
    let active = true
    api.get('/dashboard/stats').then((result) => {
      if (active) {
        setDashboard(result)
        setDashboardError('')
      }
    }).catch((error) => { if (active) setDashboardError(error.message) })
    return () => { active = false }
  }, [user, activePage])

  async function login(credentials) {
    const result = await api.post('/auth/login', credentials)
    setUser(result.user)
    setActivePage('Dashboard')
  }

  async function logout() {
    await api.post('/auth/logout', {})
    api.clearSession()
    setUser(null)
    setDashboard(null)
    setActivePage('Dashboard')
  }

  if (authLoading) return <div className="grid min-h-screen place-items-center bg-slate-50 text-xs text-muted">Checking your session…</div>
  if (!user) return <Login authError={authError} onLogin={login} />

  return <AppShell activePage={activePage} onNavigate={setActivePage} onLogout={logout} user={user}>
    <Suspense fallback={<div className="rounded-lg border border-line bg-white p-8 text-center text-xs text-muted">Loading page…</div>}>
      {activePage === 'Dashboard'
        ? dashboardError
          ? <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700" role="alert">{dashboardError}</div>
          : dashboard
            ? <Dashboard data={dashboard} onNavigate={setActivePage} user={user} />
            : <div className="rounded-lg border border-line bg-white p-8 text-center text-xs text-muted">Loading portfolio dashboard…</div>
        : <OperationalPage onNavigate={setActivePage} pageName={activePage} user={user} />}
    </Suspense>
  </AppShell>
}

export default App
