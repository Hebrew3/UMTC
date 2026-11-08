import { useEffect, useState } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import FullCalendar from '@fullcalendar/react'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import { api } from '../lib/api'

const clusters = ['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO']
const inputClass = 'h-10 w-full rounded-md border border-line bg-white px-3 text-xs text-ink outline-none focus:border-blue focus:ring-2 focus:ring-blue/10'
const labelClass = 'mb-1.5 block text-[11px] font-medium text-slate-600'

function PageTitle({ title, description, children }) {
  return <div className="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 className="text-xl font-semibold tracking-tight text-navy">{title}</h2><p className="mt-1 text-xs text-muted">{description}</p></div>{children}</div>
}

function ErrorMessage({ children }) {
  return children ? <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">{children}</div> : null
}

function Loading() {
  return <div className="rounded-lg border border-line bg-white p-8 text-center text-xs text-muted">Loading data…</div>
}

function Empty() {
  return <div className="rounded-lg border border-dashed border-line bg-white p-10 text-center text-xs text-muted">No records found. Import the workbook or adjust your filters.</div>
}

function DataTable({ columns, rows, loading, empty = <Empty /> }) {
  if (loading) return <Loading />
  if (!rows?.length) return empty
  return <div className="table-shell"><table className="data-table min-w-[760px]"><thead><tr>{columns.map((column) => <th key={column.key}>{column.label}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr key={row.id || row.account_number || row.agent_id || row.email || index}>{columns.map((column) => <td key={column.key}>{column.render ? column.render(row) : (row[column.key] ?? '—')}</td>)}</tr>)}</tbody></table></div>
}

function Pagination({ page, lastPage, onChange }) {
  if (!lastPage || lastPage <= 1) return null
  return <div className="mt-3 flex items-center justify-between text-xs text-muted"><span>Page {page} of {lastPage}</span><div className="flex gap-2"><button className="rounded border border-line bg-white px-3 py-1.5 disabled:opacity-40" disabled={page <= 1} onClick={() => onChange(page - 1)}>Previous</button><button className="rounded border border-line bg-white px-3 py-1.5 disabled:opacity-40" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Next</button></div></div>
}

function PortfolioPage({ pageName, user, onNavigate }) {
  const [query, setQuery] = useState('')
  const [filters, setFilters] = useState({})
  const [filterOptions, setFilterOptions] = useState({ clusters: [], agents: [], productLines: [], serviceTypes: [], classifications: [], statuses: [] })
  const [page, setPage] = useState(1)
  const [response, setResponse] = useState(null)
  const [loadedKey, setLoadedKey] = useState('')
  const [error, setError] = useState('')
  const [selected, setSelected] = useState(null)
  const [addOpen, setAddOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [revision, setRevision] = useState(0)
  const isNoUpdates = pageName === 'No Updates Yet'
  const endpoint = isNoUpdates ? '/monitoring/no-updates' : pageName === 'Agent By Cluster' ? '/monitoring/agent-by-cluster' : '/portfolios'
  const params = new URLSearchParams({ page: String(page), per_page: '25' })
  if (query.trim()) params.set('search', query.trim())
  if (!isNoUpdates && pageName === 'Portfolio Management') params.set('sortField', 'account_number')
  Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value) })
  const requestUrl = `${endpoint}?${params}`
  const loading = loadedKey !== requestUrl

  useEffect(() => {
    api.get('/monitoring/filters').then(setFilterOptions).catch((requestError) => setError(requestError.message))
  }, [])

  useEffect(() => {
    let active = true
    api.get(requestUrl).then((payload) => {
      if (active) { setResponse(payload); setError(''); setLoadedKey(requestUrl) }
    }).catch((requestError) => {
      if (active) { setError(requestError.message); setLoadedKey(requestUrl) }
    })
    return () => { active = false }
  }, [requestUrl, revision])

  const rows = response?.data || []
  const columns = [
    { key: 'account_number', label: 'Account number', render: (row) => <button className="font-semibold text-blue hover:underline" onClick={() => setSelected(row)}>{row.account_number}</button> },
    { key: 'account_name', label: 'Account name' },
    { key: 'agent', label: 'Field Agent', render: (row) => row.agent?.agent_name || row.source_agent_name || 'Unassigned' },
    { key: 'product_line', label: 'Product line' },
    { key: 'service_type', label: 'Service type' },
    { key: 'classification', label: 'Classification' },
    { key: 'cluster', label: 'Cluster' },
    ...(pageName === 'Agent By Cluster' ? [
      { key: 'city', label: 'Location' },
      { key: 'province', label: 'Province' },
    ] : []),
    { key: 'address', label: 'Address' },
    { key: 'latest_status', label: 'Latest status', render: (row) => <span className="rounded-full bg-amber-50 px-2 py-1 text-[10px] text-amber-800">{row.latest_status || '—'}</span> },
    { key: 'assignment_status', label: 'Assignment match', render: (row) => row.assignment_status === 'matched' ? 'Matched' : row.assignment_status === 'agent_conflict' ? 'Agent conflict' : 'Needs review' },
  ]
  const isAdmin = user.role === 'admin'
  const exportParams = new URLSearchParams()
  if (query.trim()) exportParams.set('search', query.trim())
  Object.entries(filters).forEach(([key, value]) => { if (value) exportParams.set(key, value) })
  const selectFilter = (key, label, options, optionLabel = (option) => option) => <select aria-label={label} className={`${inputClass} sm:max-w-[190px]`} key={key} onChange={(event) => { setPage(1); setFilters((current) => ({ ...current, [key]: event.target.value })) }} value={filters[key] || ''}><option value="">{label}</option>{options.map((option) => <option key={typeof option === 'object' ? option.id : option} value={typeof option === 'object' ? option.id : option}>{optionLabel(option)}</option>)}</select>

  async function addPortfolio(event) {
    event.preventDefault()
    setSaving(true); setError('')
    const formElement = event.currentTarget
    const values = Object.fromEntries(new FormData(formElement).entries())
    if (!values.assigned_agent_id) values.assigned_agent_id = null
    try {
      await api.post('/portfolios', values)
      setAddOpen(false); formElement.reset(); setPage(1); setRevision((value) => value + 1)
    } catch (requestError) { setError(requestError.message) } finally { setSaving(false) }
  }

  return <main className="mx-auto max-w-[1480px]">
    <PageTitle title={pageName} description={isNoUpdates ? 'Accounts whose latest status is marked “NO UPDATES YET” in the imported workbook.' : pageName === 'Agent By Cluster' ? 'Current accounts grouped by workbook cluster and Data Input agent, with location and province matched from the address.' : 'Search and filter the current imported portfolio data.'}>
      <div className="flex flex-wrap gap-2">{pageName === 'Portfolio Management' && isAdmin && <><button className="rounded-md border border-line bg-white px-3 py-2 text-xs font-semibold text-navy" onClick={() => setAddOpen((value) => !value)}>{addOpen ? 'Close form' : 'Add portfolio'}</button><button className="rounded-md bg-blue px-3 py-2 text-xs font-semibold text-white" onClick={() => onNavigate('Excel Import')}>Import Excel</button></>}<button className="rounded-md border border-line bg-white px-3 py-2 text-xs font-semibold text-navy" onClick={() => { window.location.href = `/api/portfolios/export?${exportParams}` }}>Export CSV</button></div>
    </PageTitle>
    <ErrorMessage>{error}</ErrorMessage>
    {addOpen && isAdmin && <form className="mb-4 grid gap-3 rounded-lg border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" onSubmit={addPortfolio}>
      {Object.entries({ account_number: 'Account number', account_name: 'Account name', address: 'Address', client: 'Client', contact: 'Contact', product_line: 'Product line', service_type: 'Service type', classification: 'Classification', latest_status: 'Latest status' }).map(([name, label]) => <label key={name}><span className={labelClass}>{label}</span><input className={inputClass} name={name} required={['account_number', 'account_name', 'address'].includes(name)} /></label>)}
      <label><span className={labelClass}>Cluster</span><select className={inputClass} name="cluster"><option value="">Select cluster</option>{filterOptions.clusters.map((cluster) => <option key={cluster}>{cluster}</option>)}</select></label>
      <label><span className={labelClass}>Field Agent</span><select className={inputClass} name="assigned_agent_id"><option value="">Unassigned</option>{filterOptions.agents.map((agent) => <option key={agent.id} value={agent.id}>{agent.agent_name} · {agent.assigned_cluster}</option>)}</select></label>
      <div className="sm:col-span-2 lg:col-span-4"><button className="rounded-md bg-blue px-4 py-2 text-xs font-semibold text-white disabled:opacity-50" disabled={saving}>{saving ? 'Saving…' : 'Save portfolio'}</button></div>
    </form>}
    <div className="mb-4 flex flex-col gap-2 rounded-lg border border-line bg-white p-3">
      <div className="flex flex-col gap-2 sm:flex-row">
        <input className={`${inputClass} sm:max-w-sm`} onChange={(event) => { setPage(1); setQuery(event.target.value) }} placeholder="Search account, name, address, or agent" value={query} />
        {selectFilter('cluster', 'All clusters', filterOptions.clusters)}
        {selectFilter('agent_id', 'All agents', filterOptions.agents, (agent) => `${agent.agent_name} · ${agent.assigned_cluster || '—'}`)}
      </div>
      {!isNoUpdates && pageName === 'Portfolio Management' && <div className="flex flex-wrap gap-2">
        {selectFilter('product_line', 'All product lines', filterOptions.productLines)}
        {selectFilter('service_type', 'All service types', filterOptions.serviceTypes)}
        {selectFilter('classification', 'All classifications', filterOptions.classifications)}
        {selectFilter('latest_status', 'All statuses', filterOptions.statuses)}
      </div>}
      {isNoUpdates && <span className="self-center text-[11px] text-muted">{response?.total ?? 0} accounts</span>}
    </div>
    <DataTable columns={columns} loading={loading} rows={rows} />
    <Pagination lastPage={response?.last_page} onChange={setPage} page={response?.current_page || page} />
    {selected && <div className="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelected(null) }}><section aria-label="Account details" aria-modal="true" className="max-h-[90vh] w-full max-w-2xl overflow-auto rounded-lg bg-white p-5 shadow-panel" role="dialog"><div className="mb-4 flex justify-between"><div><h3 className="text-lg font-semibold text-navy">{selected.account_name}</h3><p className="mt-1 text-xs text-muted">{selected.account_number}</p></div><button className="text-xs text-muted" onClick={() => setSelected(null)}>Close</button></div><dl className="grid gap-3 sm:grid-cols-2">{Object.entries({ 'Field Agent': selected.agent?.agent_name || selected.source_agent_name, Cluster: selected.cluster, Area: selected.imported_area, 'Product line': selected.product_line, 'Service type': selected.service_type, Classification: selected.classification, Status: selected.latest_status, 'Match status': selected.assignment_status, 'Match notes': selected.match_notes, Address: selected.address, Contact: selected.contact, Particulars: selected.particulars }).map(([label, value]) => <div className="rounded-md bg-slate-50 p-3" key={label}><dt className="text-[10px] uppercase text-muted">{label}</dt><dd className="mt-1 break-words text-xs text-ink">{value || '—'}</dd></div>)}</dl></section></div>}
  </main>
}

function ImportPage() {
  const [file, setFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [history, setHistory] = useState([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => { api.get('/imports').then((result) => setHistory(result.data || [])).catch((requestError) => setError(requestError.message)) }, [])

  async function upload(event) {
    event.preventDefault()
    if (!file) return
    setError(''); setNotice(''); setBusy(true)
    const form = new FormData()
    form.append('file', file)
    try { setPreview(await api.post('/imports/preview', form)) } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  async function confirmImport() {
    if (!preview) return
    setError(''); setBusy(true)
    try {
      const result = await api.post(`/imports/${preview.importId}/confirm`, {})
      setNotice(result.message)
      setPreview(null); setFile(null)
      setHistory((await api.get('/imports')).data || [])
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  return <main className="mx-auto max-w-[1480px]">
    <PageTitle title="Excel Import" description="Upload the complete monitoring workbook, review duplicates and assignment confidence, then confirm the import." />
    <ErrorMessage>{error}</ErrorMessage>{notice && <p className="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-xs text-green-800">{notice}</p>}
    <section className="mb-5 rounded-lg border border-line bg-white p-4 shadow-card sm:p-5">
      <h3 className="text-sm font-semibold text-navy">Upload a monitoring workbook</h3><p className="mt-1 text-xs text-muted">The workbook must contain Data Input and the NCR, NORTH, SOUTH, VISAYAS, and MINDANAO reference sheets. Maximum file size: 20 MB.</p>
      <form className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={upload}><label className="min-w-0 flex-1"><span className={labelClass}>Excel workbook (.xlsx or .xls)</span><input accept=".xlsx,.xls" className={inputClass} onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null) }} required type="file" /></label><button className="h-10 rounded-md bg-navy px-4 text-xs font-semibold text-white hover:bg-secondary disabled:opacity-50" disabled={busy || !file}>{busy ? 'Processing…' : 'Preview workbook'}</button></form>
    </section>
    {preview && <section className="mb-5 rounded-lg border border-line bg-white p-4 shadow-card sm:p-5">
      <div className="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h3 className="text-sm font-semibold text-navy">Import preview</h3><p className="mt-1 text-xs text-muted">{preview.fileName}</p></div><button className="rounded-md bg-blue px-4 py-2 text-xs font-semibold text-white hover:bg-secondary disabled:opacity-50" disabled={busy || preview.counts.valid_rows === 0} onClick={confirmImport}>{busy ? 'Importing…' : 'Confirm full import'}</button></div>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">{Object.entries({ 'Rows in workbook': preview.counts.total_rows, 'Unique accounts': preview.counts.unique_accounts, 'Importable accounts': preview.counts.valid_rows, 'Conflicting duplicate accounts': preview.counts.conflicting_accounts, 'Matched assignments': preview.counts.matched_accounts, 'Unmatched assignments': preview.counts.unmatched_accounts, 'Agent conflicts': preview.counts.agent_conflicts, 'Invalid rows': preview.counts.invalid_rows }).map(([label, value]) => <div className="rounded-md bg-slate-50 p-3" key={label}><p className="text-[10px] text-muted">{label}</p><p className="mt-1 text-lg font-semibold text-navy">{Number(value).toLocaleString()}</p></div>)}</div>
      {preview.issues.duplicates.length > 0 && <div className="mt-4"><h4 className="text-xs font-semibold text-amber-800">Duplicate conflicts (will be skipped)</h4><div className="mt-2 max-h-36 overflow-auto rounded border border-amber-100">{preview.issues.duplicates.map((item) => <p className="border-b border-amber-100 px-3 py-2 text-[11px] text-slate-600" key={item.account_number}>{item.account_number}: {item.reason}</p>)}</div></div>}
      {preview.issues.invalid.length > 0 && <div className="mt-4"><h4 className="text-xs font-semibold text-red-700">Invalid rows (will not be imported)</h4><div className="mt-2 max-h-36 overflow-auto rounded border border-red-100">{preview.issues.invalid.map((item, index) => <p className="border-b border-red-100 px-3 py-2 text-[11px] text-slate-600" key={`${item.account_number || 'missing'}-${index}`}>{item.account_number || 'Missing account number'}: missing {item.missing.join(', ')}</p>)}</div></div>}
      {preview.issues.assignmentWarnings.length > 0 && <div className="mt-4"><h4 className="text-xs font-semibold text-amber-800">Source-agent/reference conflicts</h4><div className="mt-2 max-h-36 overflow-auto rounded border border-amber-100">{preview.issues.assignmentWarnings.map((item) => <p className="border-b border-amber-100 px-3 py-2 text-[11px] text-slate-600" key={item.account_number}>{item.account_number}: workbook “{item.source_agent}”; reference “{item.reference_agent}”</p>)}</div></div>}
      <div className="mt-4 table-shell"><table className="data-table min-w-[760px]"><thead><tr>{['Account', 'Account name', 'Cluster', 'Location', 'Province', 'Data Input agent', 'Reference agent', 'Status'].map((heading) => <th key={heading}>{heading}</th>)}</tr></thead><tbody>{preview.sampleRows.map((row) => <tr key={row.account_number}><td>{row.account_number}</td><td>{row.account_name}</td><td>{row.cluster || '—'}</td><td>{row.location || 'Needs review'}</td><td>{row.province || 'Needs review'}</td><td>{row.source_agent || '—'}</td><td>{row.reference_agent || '—'}</td><td>{row.assignment_status}</td></tr>)}</tbody></table></div>
    </section>}
    <section><h3 className="mb-3 text-sm font-semibold text-navy">Import history</h3><DataTable columns={[{ key: 'file_name', label: 'Workbook' }, { key: 'uploaded_by', label: 'Uploaded by' }, { key: 'status', label: 'Status' }, { key: 'total_rows', label: 'Rows' }, { key: 'successful_rows', label: 'Imported/updated' }, { key: 'duplicate_rows', label: 'Duplicates' }, { key: 'invalid_rows', label: 'Invalid' }, { key: 'created_at', label: 'Date', render: (row) => new Date(row.created_at).toLocaleString() }]} rows={history} /></section>
  </main>
}

function AgentsPage() {
  const [agents, setAgents] = useState([])
  const [search, setSearch] = useState('')
  const [cluster, setCluster] = useState('')
  const [status, setStatus] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  useEffect(() => {
    let active = true
    const params = new URLSearchParams()
    if (search.trim()) params.set('search', search.trim())
    if (cluster) params.set('cluster', cluster)
    if (status) params.set('status', status)
    api.get(`/agents?${params}`).then((result) => {
      if (active) { setAgents(result); setError('') }
    }).catch((requestError) => { if (active) setError(requestError.message) })
    return () => { active = false }
  }, [search, cluster, status])

  async function save(event) {
    event.preventDefault(); setBusy(true); setError('')
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    try {
      const values = Object.fromEntries(form.entries())
      if (editing) await api.put(`/agents/${editing.id}`, values)
      else await api.post('/agents', values)
      setFormOpen(false); setEditing(null); formElement.reset()
      const params = new URLSearchParams()
      if (search.trim()) params.set('search', search.trim())
      if (cluster) params.set('cluster', cluster)
      if (status) params.set('status', status)
      api.get(`/agents?${params}`).then(setAgents).catch((requestError) => setError(requestError.message))
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  return <main className="mx-auto max-w-[1480px]"><PageTitle title="Agent Management" description="Field Agent directory and their assigned account counts."><button className="rounded-md bg-navy px-3 py-2 text-xs font-semibold text-white" onClick={() => setFormOpen((value) => !value)}>{formOpen ? 'Close form' : 'Add agent'}</button></PageTitle><ErrorMessage>{error}</ErrorMessage>
    {formOpen && <form className="mb-4 grid gap-3 rounded-lg border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-5" key={editing?.id || 'new-agent'} onSubmit={save}><h3 className="text-sm font-semibold text-navy sm:col-span-2 lg:col-span-5">{editing ? 'Edit Field Agent' : 'Add Field Agent'}</h3>{[['agent_id', 'Agent ID'], ['agent_name', 'Agent name'], ['contact_number', 'Contact number'], ['assigned_area', 'Area']].map(([key, title]) => <label key={key}><span className={labelClass}>{title}</span><input className={inputClass} defaultValue={editing?.[key] || ''} name={key} required={key !== 'contact_number' && key !== 'assigned_area'} /></label>)}<label><span className={labelClass}>Cluster</span><select className={inputClass} defaultValue={editing?.assigned_cluster || ''} name="assigned_cluster"><option value="">Select</option>{clusters.map((cluster) => <option key={cluster}>{cluster}</option>)}</select></label><label><span className={labelClass}>Status</span><select className={inputClass} defaultValue={editing?.status || 'active'} name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><div className="sm:col-span-2 lg:col-span-5"><button className="rounded-md bg-blue px-4 py-2 text-xs font-semibold text-white disabled:opacity-50" disabled={busy}>{busy ? 'Saving…' : editing ? 'Save changes' : 'Save agent'}</button></div></form>}
    <div className="mb-3 grid gap-2 sm:grid-cols-[minmax(240px,1fr)_190px_160px]">
      <input aria-label="Search agents" className={inputClass} onChange={(event) => setSearch(event.target.value)} placeholder="Search agents, IDs, areas, or clusters" value={search} />
      <select aria-label="Filter by cluster" className={inputClass} onChange={(event) => setCluster(event.target.value)} value={cluster}><option value="">All clusters</option>{clusters.map((item) => <option key={item} value={item}>{item}</option>)}</select>
      <select aria-label="Filter by status" className={inputClass} onChange={(event) => setStatus(event.target.value)} value={status}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
    <DataTable columns={[{ key: 'agent_name', label: 'Agent name' }, { key: 'agent_id', label: 'Agent ID' }, { key: 'assigned_cluster', label: 'Cluster' }, { key: 'assigned_area', label: 'Area' }, { key: 'portfolios_count', label: 'Current accounts' }, { key: 'status', label: 'Status' }, { key: 'actions', label: 'Actions', render: (agent) => <button className="font-medium text-blue hover:underline" onClick={() => { setEditing(agent); setFormOpen(true) }}>Edit</button> }]} rows={agents} />
  </main>
}

function AreasPage() {
  const [data, setData] = useState(null)
  const [cluster, setCluster] = useState('')
  const [error, setError] = useState('')
  useEffect(() => { const url = cluster ? `/monitoring/areas?cluster=${cluster}` : '/monitoring/areas'; api.get(url).then(setData).catch((requestError) => setError(requestError.message)) }, [cluster])
  const chartData = (data?.clusters || []).map((item) => ({ cluster: item.cluster, total: Number(item.total) }))
  const provinces = data?.provinces?.filter((item) => !cluster || item.cluster === cluster) || []
  const cities = data?.cities?.filter((item) => !cluster || item.cluster === cluster) || []
  return <main className="mx-auto max-w-[1480px]"><PageTitle title="Area Monitoring" description="Portfolio distribution by cluster, province, city, product line, and service type." /><ErrorMessage>{error}</ErrorMessage>
    <div className="mb-4 flex flex-wrap gap-2">{['', ...clusters].map((item) => <button className={`rounded-md border px-3 py-2 text-[11px] font-medium ${cluster === item ? 'border-navy bg-navy text-white' : 'border-line bg-white text-slate-600'}`} key={item || 'all'} onClick={() => setCluster(item)}>{item || 'All clusters'}</button>)}</div>
    {data && <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">{chartData.map((item) => <article className="rounded-lg border border-line bg-white p-4 shadow-card" key={item.cluster}><p className="text-[10px] font-semibold text-muted">{item.cluster}</p><p className="mt-2 text-2xl font-semibold text-navy">{item.total.toLocaleString()}</p><p className="mt-1 text-[10px] text-muted">current accounts</p></article>)}</div>}
    <section className="mb-4 rounded-lg border border-line bg-white p-4 shadow-card"><h3 className="mb-3 text-sm font-semibold text-navy">Accounts per cluster</h3>{chartData.length ? <div className="h-64"><ResponsiveContainer height="100%" width="100%"><BarChart data={chartData}><CartesianGrid stroke="#EDF1F5" strokeDasharray="3 3" vertical={false} /><XAxis dataKey="cluster" tick={{ fontSize: 10 }} /><YAxis tick={{ fontSize: 10 }} /><Tooltip /><Bar dataKey="total" fill="#3478C9" radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div> : <Empty />}</section>
    <div className="grid gap-4 lg:grid-cols-2"><DataTable columns={[{ key: 'cluster', label: 'Cluster' }, { key: 'province', label: 'Province' }, { key: 'total', label: 'Accounts' }]} rows={provinces} /><DataTable columns={[{ key: 'cluster', label: 'Cluster' }, { key: 'city', label: 'City / location' }, { key: 'total', label: 'Accounts' }]} rows={cities} /></div>
    <div className="mt-4 grid gap-4 lg:grid-cols-2"><DataTable columns={[{ key: 'product_line', label: 'Product line' }, { key: 'total', label: 'Accounts' }]} rows={data?.products || []} /><DataTable columns={[{ key: 'service_type', label: 'Service type' }, { key: 'total', label: 'Accounts' }]} rows={data?.services || []} /></div>
  </main>
}

function ReportsPage() {
  const [data, setData] = useState(null)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [error, setError] = useState('')
  useEffect(() => { const params = new URLSearchParams(); if (from) params.set('from', from); if (to) params.set('to', to); api.get(`/reports?${params}`).then(setData).catch((requestError) => setError(requestError.message)) }, [from, to])
  const exportParams = new URLSearchParams(); if (from) exportParams.set('from', from); if (to) exportParams.set('to', to)
  return <main className="mx-auto max-w-[1480px]"><PageTitle title="Reports & Analytics" description="Live portfolio and fieldwork summaries from imported records."><button className="rounded-md border border-line bg-white px-3 py-2 text-xs font-semibold text-navy" onClick={() => { window.location.href = `/api/portfolios/export?${exportParams}` }}>Export CSV</button></PageTitle><ErrorMessage>{error}</ErrorMessage>
    <div className="mb-4 flex flex-wrap gap-3 rounded-lg border border-line bg-white p-3"><label><span className={labelClass}>Updated from</span><input className={inputClass} onChange={(event) => setFrom(event.target.value)} type="date" value={from} /></label><label><span className={labelClass}>Updated to</span><input className={inputClass} onChange={(event) => setTo(event.target.value)} type="date" value={to} /></label></div>
    {data && <><div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">{[['Portfolio accounts', data.total], ['No updates yet', data.noUpdates], ['Unassigned', data.unassigned], ['Scheduled fieldwork', data.fieldwork]].map(([label, count]) => <article className="rounded-lg border border-line bg-white p-4 shadow-card" key={label}><p className="text-[11px] text-muted">{label}</p><p className="mt-2 text-2xl font-semibold text-navy">{Number(count).toLocaleString()}</p></article>)}</div>
      <div className="grid gap-4 lg:grid-cols-2"><DataTable columns={[{ key: 'cluster', label: 'Cluster' }, { key: 'total', label: 'Accounts' }]} rows={data.byCluster} /><DataTable columns={[{ key: 'agent', label: 'Field Agent', render: (row) => row.agent?.agent_name || '—' }, { key: 'total', label: 'Assigned accounts' }]} rows={data.byAgent} /></div>
      {Array.isArray(data.imports) && <div className="mt-4"><h3 className="mb-3 text-sm font-semibold text-navy">Recent imports</h3><DataTable columns={[{ key: 'file_name', label: 'Workbook' }, { key: 'successful_rows', label: 'Imported/updated' }, { key: 'created_at', label: 'Date', render: (row) => new Date(row.created_at).toLocaleDateString() }]} rows={data.imports} /></div>}</>}
  </main>
}

function FieldworkPage({ pageName }) {
  const [events, setEvents] = useState([])
  const [agents, setAgents] = useState([])
  const [portfolios, setPortfolios] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [open, setOpen] = useState(pageName !== 'Fieldwork History')
  const [cluster, setCluster] = useState('')
  const [selectedEvent, setSelectedEvent] = useState(null)
  const [scheduleDate, setScheduleDate] = useState('')
  const [revision, setRevision] = useState(0)

  useEffect(() => {
    let active = true
    const params = new URLSearchParams()
    if (pageName === 'Fieldwork History') params.set('to', new Date().toISOString().slice(0, 10))
    if (cluster) params.set('cluster', cluster)
    api.get(`/fieldwork?${params}`).then((result) => { if (active) setEvents(result) }).catch((requestError) => { if (active) setError(requestError.message) })
    api.get('/agents').then((result) => { if (active) setAgents(result) }).catch((requestError) => { if (active) setError(requestError.message) })
    api.get('/portfolios?per_page=100').then((result) => { if (active) setPortfolios(result.data || []) }).catch((requestError) => { if (active) setError(requestError.message) })
    return () => { active = false }
  }, [pageName, cluster, revision])

  async function save(event) {
    event.preventDefault(); setBusy(true); setError('')
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    const portfolioIds = [...event.currentTarget.elements.portfolio_ids.selectedOptions].map((option) => Number(option.value))
    const body = Object.fromEntries(form.entries())
    delete body.portfolio_ids
    body.portfolio_ids = portfolioIds
    try { await api.post('/fieldwork', body); formElement.reset(); setRevision((value) => value + 1); setOpen(false) } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }

  async function updateStatus(item, status) {
    try { await api.put(`/fieldwork/${item.id}`, { status }); setRevision((value) => value + 1) } catch (requestError) { setError(requestError.message) }
  }

  return <main className="mx-auto max-w-[1480px]"><PageTitle title={pageName} description="Schedules and fieldwork records stored by the system.">{pageName !== 'Fieldwork History' && <button className="rounded-md bg-navy px-3 py-2 text-xs font-semibold text-white" onClick={() => setOpen((value) => !value)}>{open ? 'Close form' : 'Create schedule'}</button>}</PageTitle><ErrorMessage>{error}</ErrorMessage>
    {open && pageName !== 'Fieldwork History' && <form className="mb-5 grid gap-3 rounded-lg border border-line bg-white p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4" onSubmit={save}>
      <label><span className={labelClass}>Field Agent</span><select className={inputClass} name="agent_id" required><option value="">Select agent</option>{agents.map((agent) => <option key={agent.id} value={agent.id}>{agent.agent_name} · {agent.assigned_cluster || 'No cluster'}</option>)}</select></label>
      <label className="sm:col-span-2"><span className={labelClass}>Accounts (Ctrl/Cmd-click for multiple)</span><select className={`${inputClass} h-28`} multiple name="portfolio_ids" required>{portfolios.map((item) => <option key={item.id} value={item.id}>{item.account_number} · {item.account_name}</option>)}</select></label>
      <label><span className={labelClass}>Date</span><input className={inputClass} name="schedule_date" onChange={(event) => setScheduleDate(event.target.value)} required type="date" value={scheduleDate} /></label>
      <label><span className={labelClass}>Start time</span><input className={inputClass} name="start_time" type="time" /></label>
      <label><span className={labelClass}>End time</span><input className={inputClass} name="end_time" type="time" /></label>
      <label><span className={labelClass}>Destination</span><input className={inputClass} name="location" required /></label>
      <label className="sm:col-span-2"><span className={labelClass}>Remarks</span><input className={inputClass} name="notes" /></label>
      <div className="sm:col-span-2 lg:col-span-4"><button className="rounded-md bg-blue px-4 py-2 text-xs font-semibold text-white disabled:opacity-50" disabled={busy}>{busy ? 'Saving…' : 'Save schedule'}</button></div>
    </form>}
    {pageName !== 'Fieldwork History' && <div className="mb-4 flex flex-wrap items-center gap-2"><label className="text-[11px] text-muted">Cluster</label><select className={`${inputClass} max-w-[190px]`} onChange={(event) => setCluster(event.target.value)} value={cluster}><option value="">All clusters</option>{clusters.map((item) => <option key={item}>{item}</option>)}</select></div>}
    {pageName === 'Fieldwork Calendar' && <section className="mb-4 rounded-lg border border-line bg-white p-3 shadow-card"><FullCalendar
      datesSet={(info) => { if (!scheduleDate && info.startStr) setScheduleDate(info.startStr.slice(0, 10)) }}
      dateClick={(info) => { setScheduleDate(info.dateStr); setOpen(true) }}
      eventClick={(info) => setSelectedEvent(info.event.extendedProps.schedule)}
      events={events.map((item) => ({ id: item.id, title: `${item.agent?.agent_name || 'Agent'} · ${item.portfolio?.account_name || item.title}`, start: item.start_time ? `${item.schedule_date}T${item.start_time}` : item.schedule_date, end: item.end_time ? `${item.schedule_date}T${item.end_time}` : undefined, backgroundColor: item.status === 'completed' ? '#16A36A' : item.status === 'cancelled' ? '#DC5252' : '#3478C9', borderColor: item.status === 'completed' ? '#16A36A' : item.status === 'cancelled' ? '#DC5252' : '#3478C9', extendedProps: { schedule: item } }))}
      headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }}
      height="auto"
      initialView="dayGridMonth"
      plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
    /></section>}
    {pageName !== 'Fieldwork Calendar' && <DataTable columns={[{ key: 'schedule_date', label: 'Date', render: (row) => new Date(`${row.schedule_date}T00:00:00`).toLocaleDateString() }, { key: 'start_time', label: 'Time', render: (row) => `${row.start_time?.slice(0, 5) || '—'}${row.end_time ? `–${row.end_time.slice(0, 5)}` : ''}` }, { key: 'portfolio', label: 'Account', render: (row) => `${row.portfolio?.account_number || ''} · ${row.portfolio?.account_name || row.title}` }, { key: 'agent', label: 'Agent', render: (row) => row.agent?.agent_name || '—' }, { key: 'location', label: 'Destination' }, { key: 'status', label: 'Status' }, { key: 'actions', label: 'Actions', render: (row) => row.status === 'pending' && <button className="text-blue hover:underline" onClick={() => updateStatus(row, 'completed')}>Mark completed</button> }]} rows={events} />}
    {selectedEvent && <div className="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedEvent(null) }}><section aria-modal="true" className="w-full max-w-md rounded-lg bg-white p-5 shadow-panel" role="dialog"><div className="flex items-start justify-between"><div><h3 className="text-sm font-semibold text-navy">{selectedEvent.portfolio?.account_name || selectedEvent.title}</h3><p className="mt-1 text-xs text-muted">{selectedEvent.agent?.agent_name} · {selectedEvent.location}</p></div><button className="text-xs text-muted" onClick={() => setSelectedEvent(null)}>Close</button></div><p className="mt-4 text-xs text-slate-600">{selectedEvent.schedule_date} · {selectedEvent.start_time?.slice(0, 5) || 'Time TBD'} · {selectedEvent.status}</p><p className="mt-2 text-xs text-muted">{selectedEvent.notes || 'No remarks.'}</p>{selectedEvent.status === 'pending' && <button className="mt-4 rounded-md bg-blue px-3 py-2 text-xs text-white" onClick={async () => { await updateStatus(selectedEvent, 'completed'); setSelectedEvent(null) }}>Mark completed</button>}</section></div>}
  </main>
}

function UserManagementPage() {
  const [users, setUsers] = useState([])
  const [agents, setAgents] = useState([])
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busyId, setBusyId] = useState(null)
  const [open, setOpen] = useState(false)
  const load = () => Promise.all([api.get('/users'), api.get('/agents')]).then(([userRows, agentRows]) => { setUsers(userRows); setAgents(agentRows) }).catch((requestError) => setError(requestError.message))
  useEffect(() => { load() }, [])
  async function create(event) {
    event.preventDefault(); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    const data = Object.fromEntries(form.entries())
    try { await api.post('/users', data); event.currentTarget.reset(); setOpen(false); setNotice('Senior Agent account created.'); await load() } catch (requestError) { setError(requestError.message) }
  }
  async function assign(user, event) {
    const agentIds = [...event.currentTarget.elements.agent_ids.selectedOptions].map((option) => Number(option.value))
    setBusyId(user.id); setError('')
    try { await api.put(`/users/${user.id}/agents`, { agent_ids: agentIds }); setNotice(`Assignments saved for ${user.name}.`); await load() } catch (requestError) { setError(requestError.message) } finally { setBusyId(null) }
  }
  return <main className="mx-auto max-w-[1480px]"><PageTitle title="User Management" description="Create Senior Agent accounts and manage their Field Agent scope."><button className="rounded-md bg-navy px-3 py-2 text-xs font-semibold text-white" onClick={() => setOpen((value) => !value)}>{open ? 'Close form' : 'Add Senior Agent'}</button></PageTitle><ErrorMessage>{error}</ErrorMessage>{notice && <p className="mb-3 rounded-md bg-green-50 p-3 text-xs text-green-800">{notice}</p>}
    {open && <form className="mb-4 grid gap-3 rounded-lg border border-line bg-white p-4 sm:grid-cols-2" onSubmit={create}>{[['name', 'Full name'], ['email', 'Email'], ['password', 'Password'], ['password_confirmation', 'Confirm password']].map(([name, label]) => <label key={name}><span className={labelClass}>{label}</span><input className={inputClass} name={name} required type={name.includes('password') ? 'password' : name === 'email' ? 'email' : 'text'} /></label>)}<div className="sm:col-span-2"><button className="rounded-md bg-blue px-4 py-2 text-xs font-semibold text-white">Create user</button></div></form>}
    <div className="space-y-4">{users.map((user) => <form className="rounded-lg border border-line bg-white p-4 shadow-card" key={user.id} onSubmit={(event) => { event.preventDefault(); assign(user, event) }}><div className="mb-3 flex flex-wrap items-center justify-between gap-2"><div><h3 className="text-sm font-semibold text-navy">{user.name}</h3><p className="mt-1 text-xs text-muted">{user.email} · {user.role}</p></div><button className="rounded-md bg-blue px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" disabled={busyId === user.id}>{busyId === user.id ? 'Saving…' : 'Save assignments'}</button></div><label><span className={labelClass}>Assigned Field Agents (Ctrl/Cmd-click to select multiple)</span><select className={`${inputClass} h-36`} defaultValue={user.agents.map((agent) => String(agent.id))} multiple name="agent_ids">{agents.map((agent) => <option key={agent.id} value={agent.id}>{agent.agent_name} · {agent.assigned_cluster || 'No cluster'}</option>)}</select></label></form>)}</div>
  </main>
}

function ActivityPage() {
  const [rows, setRows] = useState([])
  const [error, setError] = useState('')
  useEffect(() => { api.get('/imports').then((result) => setRows(result.data || [])).catch((requestError) => setError(requestError.message)) }, [])
  return <main className="mx-auto max-w-[1480px]"><PageTitle title="Activity Logs" description="Recorded spreadsheet import and processing activity." /><ErrorMessage>{error}</ErrorMessage><DataTable columns={[{ key: 'created_at', label: 'Time', render: (row) => new Date(row.created_at).toLocaleString() }, { key: 'uploaded_by', label: 'User' }, { key: 'file_name', label: 'Workbook' }, { key: 'status', label: 'Result' }, { key: 'successful_rows', label: 'Accounts updated' }, { key: 'duplicate_rows', label: 'Duplicates' }, { key: 'invalid_rows', label: 'Invalid rows' }]} rows={rows} /></main>
}

function SettingsPage({ user }) {
  return <main className="mx-auto max-w-3xl"><PageTitle title="Settings" description="Your current system account." /><section className="rounded-lg border border-line bg-white p-5 shadow-card"><dl className="grid gap-4 sm:grid-cols-2"><div><dt className="text-[10px] uppercase text-muted">Name</dt><dd className="mt-1 text-sm font-medium text-ink">{user.name}</dd></div><div><dt className="text-[10px] uppercase text-muted">Email</dt><dd className="mt-1 text-sm font-medium text-ink">{user.email}</dd></div><div><dt className="text-[10px] uppercase text-muted">Role</dt><dd className="mt-1 text-sm font-medium text-ink">{user.role}</dd></div></dl><p className="mt-5 border-t border-line pt-4 text-xs text-muted">Account profile editing is disabled; contact an administrator to change account details.</p></section></main>
}

export default function OperationalPage({ pageName, user, onNavigate }) {
  if (['Portfolio Management', 'Agent By Cluster', 'No Updates Yet'].includes(pageName)) return <PortfolioPage onNavigate={onNavigate} pageName={pageName} user={user} />
  if (pageName === 'Excel Import') return <ImportPage />
  if (pageName === 'Agent Management') return <AgentsPage />
  if (pageName === 'Area Monitoring') return <AreasPage />
  if (pageName === 'Reports & Analytics') return <ReportsPage />
  if (['Fieldwork Calendar', 'Schedule Management', 'Fieldwork History'].includes(pageName)) return <FieldworkPage pageName={pageName} />
  if (pageName === 'User Management') return <UserManagementPage />
  if (pageName === 'Activity Logs') return <ActivityPage />
  if (pageName === 'Settings') return <SettingsPage user={user} />
  return <main className="mx-auto max-w-3xl"><PageTitle title={pageName} description="This section is not available for your role." /></main>
}
