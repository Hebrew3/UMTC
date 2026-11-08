import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import {
  ArrowRight, BriefcaseBusiness, CalendarDays, Clock3, UsersRound, UserRoundPlus,
} from 'lucide-react'

const colors = ['#3478C9', '#16A36A', '#E9A23B', '#DC5252', '#7B61A8', '#48A7A2']
const numberFormat = new Intl.NumberFormat('en-US')

function Card({ title, subtitle, action, children, className = '' }) {
  return (
    <article className={`rounded-lg border border-line bg-white p-4 shadow-card sm:p-5 ${className}`}>
      <div className="mb-5 flex items-start justify-between gap-3">
        <div><h2 className="text-[14px] font-semibold text-ink">{title}</h2>{subtitle && <p className="mt-1 text-[11px] text-muted">{subtitle}</p>}</div>
        {action}
      </div>
      {children}
    </article>
  )
}

function Empty({ children = 'No imported data is available yet.' }) {
  return <div className="grid min-h-40 place-items-center text-center text-xs text-muted">{children}</div>
}

function ChartTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null
  return <div className="rounded-md border border-line bg-white px-3 py-2 text-[11px] shadow-panel"><p className="mb-1 font-semibold text-ink">{label}</p>{payload.map((item) => <p className="text-muted" key={item.dataKey}>{item.name}: {numberFormat.format(item.value)}</p>)}</div>
}

function monthLabel(value) {
  if (!value) return ''
  const date = new Date(`${value}-01T00:00:00`)
  return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' })
}

export default function Dashboard({ user, data, onNavigate }) {
  const stats = data.stats || {}
  const assignedRate = stats.totalPortfolioAccounts
    ? Math.round((stats.totalAssignedAccounts / stats.totalPortfolioAccounts) * 100)
    : 0
  const metricItems = [
    { label: 'Portfolio accounts', value: stats.totalPortfolioAccounts, detail: 'Current imported accounts', icon: BriefcaseBusiness, tone: 'blue' },
    { label: 'Total agents', value: stats.totalAgents, detail: 'Agents in your scope', icon: UsersRound, tone: 'violet' },
    { label: 'Assigned accounts', value: stats.totalAssignedAccounts, detail: `${assignedRate}% of current accounts`, icon: UsersRound, tone: 'green' },
    { label: 'Unassigned accounts', value: stats.totalUnassignedAccounts, detail: 'Need an assignment match', icon: UserRoundPlus, tone: 'amber' },
    { label: 'No updates yet', value: stats.totalAccountsNoUpdates, detail: 'Marked in the imported workbook', icon: Clock3, tone: 'rose' },
    { label: 'Scheduled fieldwork', value: stats.totalScheduledFieldwork, detail: 'Upcoming scheduled visits', icon: CalendarDays, tone: 'teal' },
  ]
  const trend = (data.monthlyMonitoring || []).map((item) => ({
    month: monthLabel(item.month),
    updated: Number(item.total),
    scheduled: Number((data.monthlyFieldwork || []).find((schedule) => schedule.month === item.month)?.total || 0),
  }))
  const clusterData = (data.clusterDistribution || []).map((item) => ({ cluster: item.name, accounts: Number(item.total) }))
  const statusData = (data.statusDistribution || []).map((item, index) => ({ name: item.name, value: Number(item.total), color: colors[index % colors.length] }))
  const products = data.productDistribution || []
  const hasImportedData = Number(stats.totalPortfolioAccounts || 0) > 0

  return (
    <main className="mx-auto max-w-[1480px]">
      <section className="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-blue">Welcome, {user.name}</p>
          <h2 className="text-[24px] font-semibold leading-tight tracking-[-0.035em] text-navy sm:text-[28px]">Portfolio overview</h2>
          <p className="mt-1.5 max-w-2xl text-[12px] leading-5 text-muted">Live account monitoring and field operations from the latest imported workbook.</p>
        </div>
        <span className="text-[11px] text-muted">{new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</span>
      </section>

      <section aria-label="Portfolio summary" className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        {metricItems.map(({ label, value, detail, icon: Icon, tone }) => (
          <article className="rounded-lg border border-line bg-white p-4 shadow-card transition hover:-translate-y-0.5 hover:shadow-panel sm:p-5" key={label}>
            <div className="flex items-start justify-between gap-2"><p className="text-[12px] font-medium leading-5 text-slate-600">{label}</p><span className={`metric-icon metric-icon-${tone} grid size-8 shrink-0 place-items-center rounded-md`}><Icon size={16} strokeWidth={1.8} /></span></div>
            <p className="mt-3 text-[26px] font-semibold leading-none tracking-[-0.04em] text-navy sm:text-[28px]">{numberFormat.format(Number(value || 0))}</p>
            <p className="mt-2 text-[10px] text-muted">{detail}</p>
          </article>
        ))}
      </section>

      {!hasImportedData && <div className="mb-5 flex flex-col items-start justify-between gap-3 rounded-lg border border-blue/20 bg-blue/5 p-4 sm:flex-row sm:items-center"><div><p className="text-sm font-semibold text-navy">Import the monitoring workbook to get started</p><p className="mt-1 text-xs text-muted">All dashboard counts and reports are calculated from the imported workbook.</p></div><button className="rounded-md bg-navy px-3 py-2 text-xs font-semibold text-white hover:bg-secondary" onClick={() => onNavigate('Excel Import')}>Open Excel Import</button></div>}

      <section className="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-12">
        <Card title="Monitoring activity" subtitle="Imported account updates compared with scheduled fieldwork" action={<button className="inline-flex items-center gap-1 text-[10px] font-semibold text-blue hover:text-navy" onClick={() => onNavigate('Reports & Analytics')}>View report <ArrowRight size={13} /></button>} className="xl:col-span-7">
          {trend.length ? <div className="h-[250px]">
            <ResponsiveContainer height="100%" width="100%">
              <AreaChart data={trend} margin={{ top: 8, right: 6, left: -18, bottom: 0 }}>
                <defs><linearGradient id="accountUpdates" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#3478C9" stopOpacity={0.14} /><stop offset="95%" stopColor="#3478C9" stopOpacity={0} /></linearGradient></defs>
                <CartesianGrid stroke="#EDF1F5" strokeDasharray="3 3" vertical={false} /><XAxis axisLine={false} dataKey="month" tick={{ fill: '#8A96A7', fontSize: 10 }} tickLine={false} /><YAxis axisLine={false} tick={{ fill: '#8A96A7', fontSize: 10 }} tickLine={false} /><Tooltip content={<ChartTooltip />} />
                <Area dataKey="updated" fill="url(#accountUpdates)" name="Accounts updated" stroke="#3478C9" strokeWidth={2} type="monotone" /><Area dataKey="scheduled" fill="none" name="Scheduled fieldwork" stroke="#16A36A" strokeWidth={2} type="monotone" />
              </AreaChart>
            </ResponsiveContainer>
          </div> : <Empty>No account updates are recorded yet.</Empty>}
        </Card>
        <Card title="Portfolio by cluster" subtitle="Current account distribution across clusters" className="xl:col-span-5">
          {clusterData.length ? <div className="h-[250px]">
            <ResponsiveContainer height="100%" width="100%"><BarChart data={clusterData} layout="vertical" margin={{ top: 4, right: 8, left: 10, bottom: 0 }}><CartesianGrid stroke="#EDF1F5" strokeDasharray="3 3" horizontal={false} /><XAxis axisLine={false} type="number" tick={{ fill: '#8A96A7', fontSize: 10 }} tickLine={false} /><YAxis axisLine={false} dataKey="cluster" type="category" width={86} tick={{ fill: '#64748B', fontSize: 10 }} tickLine={false} /><Tooltip content={<ChartTooltip />} /><Bar dataKey="accounts" fill="#3478C9" radius={[0, 4, 4, 0]} /></BarChart></ResponsiveContainer>
          </div> : <Empty />}
        </Card>
      </section>

      <section className="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card title="Status distribution" subtitle="Status values in the imported workbook">
          {statusData.length ? <div className="grid min-h-[220px] grid-cols-2 items-center">
            <div className="h-[210px]"><ResponsiveContainer height="100%" width="100%"><PieChart><Pie data={statusData} dataKey="value" innerRadius={55} outerRadius={78} paddingAngle={3} stroke="none">{statusData.map((item) => <Cell fill={item.color} key={item.name} />)}</Pie><Tooltip content={<ChartTooltip />} /></PieChart></ResponsiveContainer></div>
            <div className="space-y-3">{statusData.slice(0, 6).map((item) => <div className="flex items-center justify-between gap-2 text-[11px]" key={item.name}><span className="flex min-w-0 items-center gap-2 text-slate-600"><i className="size-2 shrink-0 rounded-full" style={{ backgroundColor: item.color }} /> <span className="truncate">{item.name}</span></span><strong className="text-ink">{numberFormat.format(item.value)}</strong></div>)}</div>
          </div> : <Empty />}
        </Card>
        <Card title="Product line" subtitle="Accounts by product line">
          {products.length ? <div className="space-y-4">{products.slice(0, 6).map((item, index) => {
            const percent = stats.totalPortfolioAccounts ? Math.round((item.total / stats.totalPortfolioAccounts) * 100) : 0
            return <div key={item.name}><div className="mb-1.5 flex justify-between gap-3 text-[11px]"><span className="truncate text-slate-600">{item.name}</span><span className="font-semibold text-ink">{numberFormat.format(item.total)}</span></div><div className="h-1.5 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full" style={{ width: `${percent}%`, backgroundColor: colors[index % colors.length] }} /></div></div>
          })}</div> : <Empty />}
        </Card>
        <Card title="Agent portfolio" subtitle="Assigned accounts per Field Agent">
          {data.agentPerformance?.length ? <div className="space-y-3">{data.agentPerformance.slice(0, 6).map((agent) => <div className="flex items-center justify-between gap-3 border-b border-line pb-2 last:border-0" key={agent.id}><div className="min-w-0"><p className="truncate text-[11px] font-semibold text-ink">{agent.agent_name}</p><p className="mt-0.5 text-[10px] text-muted">{agent.assigned_cluster || 'Cluster not assigned'}</p></div><span className="text-xs font-semibold text-navy">{numberFormat.format(agent.portfolios_count || 0)}</span></div>)}</div> : <Empty />}
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Card title="Recent import activity" subtitle="Completed workbook imports">
          {data.recentActivities?.length ? <div className="space-y-3">{data.recentActivities.map((item, index) => <div className="flex gap-3 border-b border-line pb-3 last:border-0" key={`${item.title}-${index}`}><span className="mt-1 size-2 shrink-0 rounded-full bg-blue" /><div><p className="text-xs font-semibold text-ink">{item.title}</p><p className="mt-1 text-[11px] text-muted">{item.detail}</p><p className="mt-1 text-[10px] text-muted">{new Date(item.timestamp).toLocaleString()}</p></div></div>)}</div> : <Empty>No completed workbook imports yet.</Empty>}
        </Card>
        <Card title="Upcoming fieldwork" subtitle="Next scheduled agent visits">
          {data.upcomingFieldwork?.length ? <div className="space-y-3">{data.upcomingFieldwork.map((item) => <div className="flex items-center justify-between gap-4 border-b border-line pb-3 last:border-0" key={item.id}><div className="min-w-0"><p className="truncate text-xs font-semibold text-ink">{item.portfolio?.account_name || item.title}</p><p className="mt-1 truncate text-[10px] text-muted">{item.agent?.agent_name} · {item.location}</p></div><div className="shrink-0 text-right"><p className="text-[11px] font-semibold text-navy">{new Date(`${item.schedule_date}T00:00:00`).toLocaleDateString()}</p><p className="mt-1 text-[10px] text-muted">{item.start_time?.slice(0, 5) || 'Time TBD'}</p></div></div>)}</div> : <Empty>No upcoming schedules are recorded.</Empty>}
        </Card>
      </section>

      <section className="mt-4">
        <Card title="Latest portfolio updates" subtitle="Most recently imported account records" action={<button className="inline-flex items-center gap-1 text-[10px] font-semibold text-blue hover:text-navy" onClick={() => onNavigate('Portfolio Management')}>View portfolio <ArrowRight size={13} /></button>}>
          {data.latestUpdates?.length ? <div className="table-shell border-0 shadow-none"><table className="data-table min-w-[680px]"><thead><tr><th>Account</th><th>Account name</th><th>Agent</th><th>Status</th><th>Updated</th></tr></thead><tbody>{data.latestUpdates.map((account) => <tr key={account.id}><td className="font-medium text-navy">{account.account_number}</td><td className="text-slate-700">{account.account_name}</td><td>{account.agent?.agent_name || account.source_agent_name || 'Unassigned'}</td><td><span className="rounded-full bg-amber-50 px-2 py-1 text-[10px] text-amber-800">{account.latest_status || 'No status'}</span></td><td className="text-muted">{account.last_updated_at ? new Date(account.last_updated_at).toLocaleDateString() : '—'}</td></tr>)}</tbody></table></div> : <Empty />}
        </Card>
      </section>
    </main>
  )
}
