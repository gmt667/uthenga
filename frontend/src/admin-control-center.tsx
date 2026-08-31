import { useEffect, useMemo, useState } from 'react';
import {
  Activity, AlertTriangle, BarChart3, Bell, Bot, Building2, CalendarCheck,
  CheckCircle2, ChevronLeft, ChevronRight, CircleDollarSign, Database,
  FileText, Flag, Headphones, LayoutDashboard, ListChecks, LockKeyhole,
  Menu, MessageSquare, PackageSearch, ReceiptText, Search, Settings,
  ShieldCheck, ShoppingBag, Sparkles, TicketCheck, Users, WalletCards,
  X, Zap,
} from 'lucide-react';
import './admin-control-center.css';

type AdminBoot = {
  user?: { id: string; name: string; role: string };
  features: Record<string, boolean>;
  legacy_fallbacks: Record<string, string>;
};

type Attention = { key: string; label: string; count: number; priority: string; href: string };
type ActivityRow = { at: string; type: string; title: string; detail: string };
type AdminData = {
  environment: { name: string; label: string };
  permissions: string[];
  links: Record<string, string>;
  metrics: { customers: number; vendors: number; active_bookings: number; today_revenue: number; today_commission: number | null; active_journeys: number | null };
  attention: Attention[];
  health: { name: string; status: string; detail: string }[];
  activity: ActivityRow[];
  listing_distribution: { listing_type: string; total: number; active_count: number }[];
  inventory_quality: { total: number; complete: number; missing_coordinates: number; missing_location: number; inactive: number; invalid: number; complete_percent: number | null };
  vendors: { id: string; business_name: string; owner_name: string; email: string; category: string; status: string; created_at: string }[];
  customers: { id: string; name: string; email: string; account_status: string; last_login_at: string | null; created_at: string }[];
  bookings: { id: string; booking_code: string; booking_status: string; payment_status: string; grand_total: number; occurred_at: string }[];
  payments: {
    summary: { today_volume: number; successful: number; pending: number; failed: number; refunded: number; success_rate: number | null };
    recent: { transaction_reference: string; status: string; amount: number; currency: string; created_at: string }[];
  };
  support: { id: string; ticket_code: string; subject: string; priority: string; status: string; category: string; created_at: string }[];
  notifications: { stored: number; unread: number; delivery_instrumented: boolean };
  audit: { id: string; user_name: string; user_role: string; action: string; details: string; created_at: string }[];
  telemetry: {
    recording: boolean; trace_recording: boolean; requests_today: number | null; ai_requests_today: number | null;
    ai_latency_ms: number | null; provider_failures_today: number | null; input_tokens_today: number | null;
    output_tokens_today: number | null; recent_traces: any[]; providers: any[];
    configuration: { tie_enabled: boolean; ai_provider: string | null; ai_model: string | null };
  };
  capabilities: Record<string, boolean>;
};

type AdminResponse = { success: boolean; control_center: AdminData; error?: { message?: string } };
type Section = { id: string; label: string; icon: any; badge?: (data: AdminData) => number };

const navGroups: { label: string; items: Section[] }[] = [
  { label: 'Command center', items: [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'operations', label: 'Operations', icon: Activity },
  ] },
  { label: 'Platform', items: [
    { id: 'vendors', label: 'Vendors', icon: Building2, badge: d => d.attention.find(a => a.key === 'vendors')?.count || 0 },
    { id: 'customers', label: 'Customers', icon: Users },
    { id: 'marketplace', label: 'Marketplace', icon: PackageSearch },
    { id: 'bookings', label: 'Bookings', icon: CalendarCheck },
    { id: 'payments', label: 'Payments', icon: WalletCards, badge: d => d.attention.find(a => a.key === 'payments')?.count || 0 },
    { id: 'shop', label: 'Shop', icon: ShoppingBag },
    { id: 'events', label: 'Events & tickets', icon: TicketCheck },
    { id: 'content', label: 'Content', icon: FileText },
  ] },
  { label: 'Intelligence', items: [
    { id: 'tie', label: 'TIE / AI', icon: Bot },
    { id: 'journeys', label: 'Journeys', icon: Zap },
    { id: 'analytics', label: 'Analytics', icon: BarChart3 },
    { id: 'notifications', label: 'Notifications', icon: Bell, badge: d => d.notifications.unread },
  ] },
  { label: 'Governance', items: [
    { id: 'system', label: 'System health', icon: Database },
    { id: 'security', label: 'Security', icon: ShieldCheck },
    { id: 'settings', label: 'Settings', icon: Settings },
    { id: 'support', label: 'Support', icon: Headphones, badge: d => d.attention.find(a => a.key === 'support')?.count || 0 },
  ] },
];

const allSections = navGroups.flatMap(group => group.items);
export const formatAdminCurrency = (value: number | null, compact = false) => value === null
  ? 'Unavailable'
  : new Intl.NumberFormat('en-MW', { style: 'currency', currency: 'MWK', maximumFractionDigits: 0, notation: compact ? 'compact' : 'standard' }).format(value);
const when = (value?: string | null) => value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'Not recorded';
const titleCase = (value = '') => value.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());

function adminApiUrl(path: string) {
  const configured = (window as any).UTHENGA_BASE_URL as string | undefined;
  const base = (configured || '/uthenga/').replace(/\/$/, '');
  return `${base}/api/tie/${path}`;
}

export function AdminControlCenter({ boot }: { boot: AdminBoot }) {
  const [data, setData] = useState<AdminData | null>(null);
  const [error, setError] = useState('');
  const [active, setActive] = useState('overview');
  const [sidebar, setSidebar] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const [palette, setPalette] = useState(false);
  const [query, setQuery] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    fetch(adminApiUrl('admin/control-center.php'), { credentials: 'include', signal: controller.signal })
      .then(async response => {
        const body = await response.json().catch(() => ({})) as AdminResponse;
        if (!response.ok || !body.success) throw new Error(body.error?.message || 'The Admin Control Center could not load.');
        return body.control_center;
      })
      .then(setData)
      .catch(problem => { if (problem.name !== 'AbortError') setError(problem.message); });
    return () => controller.abort();
  }, []);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); setPalette(true); }
      if (event.key === 'Escape') setPalette(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const permitted = useMemo(() => data ? new Set(data.permissions) : new Set<string>(), [data]);
  const commands = useMemo(() => {
    if (!data) return [];
    const sections = allSections.filter(item => permitted.has(item.id)).map(item => ({ label: item.label, meta: 'Open control module', run: () => setActive(item.id) }));
    const vendors = data.vendors.map(vendor => ({ label: vendor.business_name, meta: `Vendor · ${vendor.status}`, run: () => setActive('vendors') }));
    const bookings = data.bookings.map(booking => ({ label: booking.booking_code, meta: `Booking · ${booking.booking_status}`, run: () => setActive('bookings') }));
    return [...sections, ...vendors, ...bookings].filter(item => `${item.label} ${item.meta}`.toLowerCase().includes(query.toLowerCase())).slice(0, 12);
  }, [data, permitted, query]);

  if (error) return <main className="admin-cc-state"><AlertTriangle/><h1>Admin console unavailable</h1><p>{error}</p><a href={boot.legacy_fallbacks?.login}>Return to secure sign in</a></main>;
  if (!data) return <main className="admin-cc-state"><span className="admin-cc-loader"/><h1>Opening Uthenga operations</h1><p>Reading current platform health and actionable queues…</p></main>;

  const current = allSections.find(item => item.id === active) || allSections[0];
  const firstName = boot.user?.name?.split(' ')[0] || 'Administrator';
  const openSection = (id: string) => { setActive(id); setSidebar(false); setPalette(false); setQuery(''); };

  return <main className={`admin-cc ${collapsed ? 'is-collapsed' : ''}`}>
    <aside className={`admin-cc__sidebar ${sidebar ? 'is-open' : ''}`}>
      <header className="admin-cc__brand"><span className="admin-cc__brandmark"><Sparkles/></span><div><b>UTHENGA</b><small>Admin Control Center</small></div><button onClick={() => setSidebar(false)} aria-label="Close navigation"><X/></button></header>
      <div className="admin-cc__env"><i className={data.environment.name === 'production' ? 'is-production' : ''}/><span>{data.environment.label}</span></div>
      <nav>{navGroups.filter(group => group.items.some(item => permitted.has(item.id))).map(group => <section key={group.label}><p>{group.label}</p>{group.items.filter(item => permitted.has(item.id)).map(item => { const Icon = item.icon; const badge = item.badge?.(data) || 0; return <button key={item.id} className={active === item.id ? 'is-active' : ''} onClick={() => openSection(item.id)} title={item.label}><Icon/><span>{item.label}</span>{badge > 0 && <em>{badge}</em>}</button>; })}</section>)}</nav>
      <footer><div className="admin-cc__identity"><span>{firstName[0]?.toUpperCase()}</span><div><b>{boot.user?.name}</b><small>{boot.user?.role}</small></div></div><button onClick={() => setCollapsed(value => !value)}><ChevronLeft/><span>{collapsed ? 'Expand' : 'Collapse'}</span></button></footer>
    </aside>
    {sidebar && <button className="admin-cc__scrim" aria-label="Close navigation" onClick={() => setSidebar(false)}/>} 
    <section className="admin-cc__main">
      <header className="admin-cc__topbar">
        <button className="admin-cc__menu" onClick={() => setSidebar(true)} aria-label="Open navigation"><Menu/></button>
        <button className="admin-cc__search" onClick={() => setPalette(true)}><Search/><span>Search customers, vendors, bookings, payments…</span><kbd>Ctrl K</kbd></button>
        <div className="admin-cc__top-actions"><button title="Action center" onClick={() => openSection('operations')}><Bell/><em>{data.attention.reduce((sum, item) => sum + item.count, 0)}</em></button><button className="admin-cc__avatar" onClick={() => { window.location.href = data.links.profile; }}>{firstName[0]?.toUpperCase()}</button></div>
      </header>
      <div className="admin-cc__workspace">
        <header className="admin-cc__heading"><div><p>UTHENGA / {current.label.toUpperCase()}</p><h1>{active === 'overview' ? `Good ${new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 18 ? 'afternoon' : 'evening'}, ${firstName}.` : current.label}</h1><span>{active === 'overview' ? 'Here is the verified state of the Uthenga ecosystem right now.' : sectionDescription(active)}</span></div><button onClick={() => setPalette(true)}><Zap/> Quick command</button></header>
        <AdminSection active={active} data={data} boot={boot} openSection={openSection}/>
      </div>
    </section>
    {palette && <div className="admin-cc__palette-backdrop" onMouseDown={() => setPalette(false)}><section className="admin-cc__palette" onMouseDown={event => event.stopPropagation()}><header><Search/><input autoFocus value={query} onChange={event => setQuery(event.target.value)} placeholder="Search or execute an admin action…"/><button onClick={() => setPalette(false)}><X/></button></header><div>{commands.length ? commands.map((command, index) => <button key={`${command.label}-${index}`} onClick={() => { command.run(); setPalette(false); }}><span><b>{command.label}</b><small>{command.meta}</small></span><ChevronRight/></button>) : <p>No authorised command matches this search.</p>}</div><footer>Results are limited to your administrative permissions.</footer></section></div>}
  </main>;
}

export function sectionDescription(section: string) {
  const descriptions: Record<string, string> = {
    operations: 'System health, live activity and queues that require intervention.', vendors: 'Review, verify and govern marketplace service providers.', customers: 'Privacy-aware customer account operations.', marketplace: 'Global inventory and catalogue quality control.', bookings: 'Booking state, payment state and operational audit context.', payments: 'Payment ledger health, exceptions and reconciliation readiness.', shop: 'Uthenga-owned product and order operations.', tie: 'AI configuration, telemetry, cost evidence and recommendation operations.', journeys: 'Upcoming, active and interrupted journey operations.', events: 'Event catalogue, ticket capacity and check-in operations.', content: 'Promotions, announcements and customer-facing content.', notifications: 'Stored notifications and delivery-channel health.', analytics: 'Verified business and operational performance.', system: 'Backend capabilities, provider status and request tracing.', security: 'Administrative activity, access control and audit evidence.', settings: 'Feature flags and governed platform configuration.', support: 'Customer and vendor cases requiring a response.',
  };
  return descriptions[section] || 'Authoritative platform operations.';
}

function AdminSection({ active, data, boot, openSection }: { active: string; data: AdminData; boot: AdminBoot; openSection: (id: string) => void }) {
  if (active === 'overview') return <Overview data={data} openSection={openSection}/>;
  if (active === 'operations') return <Operations data={data}/>;
  if (active === 'vendors') return <Vendors data={data}/>;
  if (active === 'customers') return <Customers data={data}/>;
  if (active === 'marketplace') return <Marketplace data={data}/>;
  if (active === 'bookings') return <Bookings data={data}/>;
  if (active === 'payments') return <Payments data={data}/>;
  if (active === 'tie') return <TieOperations data={data}/>;
  if (active === 'notifications') return <Notifications data={data}/>;
  if (active === 'security') return <Security data={data}/>;
  if (active === 'support') return <Support data={data}/>;
  if (active === 'settings') return <SettingsPanel data={data} boot={boot}/>;
  if (active === 'analytics') return <Analytics data={data}/>;
  if (active === 'system') return <SystemHealth data={data}/>;
  if (active === 'events') return <ListingCategory data={data} type="event" title="Event & ticket operations"/>;
  if (active === 'shop') return <CapabilityPanel enabled={data.capabilities.shop} icon={<ShoppingBag/>} title="Uthenga Shop operations" body="Products, inventory and orders are managed by Uthenga. The current database does not yet expose the normalized shop catalogue required by this console." href={data.links.shop}/>;
  if (active === 'journeys') return <CapabilityPanel enabled={data.capabilities.journeys} icon={<Zap/>} title="Journey operations" body="No authoritative journey ledger is available in this database. Trip plans and bookings are not presented as live journeys."/>;
  return <CapabilityPanel enabled={false} icon={<FileText/>} title={titleCase(active)} body="This domain remains available through the existing authorised operation while its versioned React contract is completed." href={data.links[active]}/>;
}

function Overview({ data, openSection }: { data: AdminData; openSection: (id: string) => void }) {
  return <div className="admin-cc__dashboard">
    <section className="admin-cc__kpis">
      <Kpi label="Customers" value={data.metrics.customers.toLocaleString()} note="Registered customer accounts" icon={<Users/>}/>
      <Kpi label="Approved vendors" value={data.metrics.vendors.toLocaleString()} note="Marketplace providers" icon={<Building2/>}/>
      <Kpi label="Active bookings" value={data.metrics.active_bookings.toLocaleString()} note="Pending or confirmed" icon={<CalendarCheck/>}/>
      <Kpi label="Today's revenue" value={formatAdminCurrency(data.metrics.today_revenue, true)} note="Paid booking value" icon={<CircleDollarSign/>}/>
      <Kpi label="Commission" value={data.metrics.today_commission === null ? 'Not tracked' : formatAdminCurrency(data.metrics.today_commission, true)} note="Today’s platform commission" icon={<ReceiptText/>}/>
      <Kpi label="Active journeys" value={data.metrics.active_journeys === null ? 'Unavailable' : data.metrics.active_journeys.toLocaleString()} note="Journey ledger state" icon={<Zap/>}/>
    </section>
    <section className="admin-cc__overview-grid">
      <Panel className="admin-cc__health" title="System health" action={<button onClick={() => openSection('system')}>View operations <ChevronRight/></button>}><div>{data.health.map(item => <article key={item.name}><i className={`is-${item.status}`}/><p><b>{item.name}</b><small>{item.detail}</small></p><em>{titleCase(item.status)}</em></article>)}</div></Panel>
      <Panel className="admin-cc__attention" title="Needs your attention" action={<button onClick={() => openSection('operations')}>Open queue <ChevronRight/></button>}><div>{data.attention.filter(item => item.count > 0).length ? data.attention.filter(item => item.count > 0).map(item => <button key={item.key} onClick={() => openSection(item.key === 'listings' ? 'marketplace' : item.key)}><span className={`is-${item.priority}`}>{item.count}</span><p><b>{item.label}</b><small>Requires authorised review</small></p><ChevronRight/></button>) : <div className="admin-cc__empty"><CheckCircle2/><b>No recorded action queue</b><p>There are no open items in the currently instrumented workflows.</p></div>}</div></Panel>
      <Panel className="admin-cc__activity" title="Live platform activity" action={<span className="admin-cc__live"><i/> RECENT</span>}><ActivityFeed rows={data.activity}/></Panel>
      <Panel className="admin-cc__distribution" title="Marketplace distribution" action={<button onClick={() => openSection('marketplace')}>Quality center <ChevronRight/></button>}><Distribution data={data}/></Panel>
    </section>
  </div>;
}

function Operations({ data }: { data: AdminData }) { return <Grid><Panel title="Operational health"><HealthRows data={data}/></Panel><Panel title="Action queue"><AttentionRows data={data}/></Panel><Panel wide title="Recent platform activity"><ActivityFeed rows={data.activity}/></Panel></Grid>; }
function Vendors({ data }: { data: AdminData }) { return <Grid><Panel wide title="Vendor verification queue" action={<span className="admin-cc__count">{data.vendors.length} visible</span>}><DataTable columns={['Business', 'Owner', 'Category', 'Status', 'Submitted']} rows={data.vendors.map(row => [row.business_name, `${row.owner_name}\n${row.email}`, titleCase(row.category), <Status value={row.status}/>, when(row.created_at)])}/><div className="admin-cc__panel-actions"><a href={data.links.vendor_reviews}>Review vendor accounts</a><a href={data.links.service_reviews}>Review service profiles</a></div></Panel></Grid>; }
function Customers({ data }: { data: AdminData }) { return <Grid><Panel wide title="Recently registered customers"><DataTable columns={['Customer', 'Email', 'Account', 'Last login', 'Joined']} rows={data.customers.map(row => [row.name, row.email, <Status value={row.account_status || 'active'}/>, when(row.last_login_at), when(row.created_at)])}/></Panel><CapabilityPanel enabled={false} icon={<LockKeyhole/>} title="Privacy boundary active" body="This console exposes only account and service-operation data. Location history and private AI conversation content are not included."/></Grid>; }
function Marketplace({ data }: { data: AdminData }) { const q = data.inventory_quality; return <Grid><Panel title="Inventory quality"><div className="admin-cc__quality"><strong>{q.complete_percent === null ? '—' : `${q.complete_percent}%`}</strong><p>Recommendation-ready records</p><progress value={q.complete} max={Math.max(1, q.total)}/><ul><li><span>Complete</span><b>{q.complete}</b></li><li><span>Missing coordinates</span><b>{q.missing_coordinates}</b></li><li><span>Missing location</span><b>{q.missing_location}</b></li><li><span>Inactive</span><b>{q.inactive}</b></li><li><span>Invalid</span><b>{q.invalid}</b></li></ul></div></Panel><Panel title="Catalogue distribution"><Distribution data={data}/></Panel><Panel wide title="Intelligence impact"><div className="admin-cc__callout"><Sparkles/><div><b>{q.missing_coordinates} listing{q.missing_coordinates === 1 ? '' : 's'} cannot participate reliably in nearby recommendations.</b><p>Location quality directly affects TIE search, ranking and routing. Complete vendor coordinates before treating the catalogue as geographically ready.</p></div><a href={data.links.marketplace}>Review listings</a></div></Panel></Grid>; }
function Bookings({ data }: { data: AdminData }) { return <Grid><Panel wide title="Recent booking operations"><DataTable columns={['Booking', 'Booking state', 'Payment state', 'Amount', 'Recorded']} rows={data.bookings.map(row => [row.booking_code, <Status value={row.booking_status}/>, <Status value={row.payment_status}/>, formatAdminCurrency(Number(row.grand_total)), when(row.occurred_at)])}/><div className="admin-cc__panel-actions"><a href={data.links.bookings}>Open booking operations</a></div></Panel></Grid>; }
function Payments({ data }: { data: AdminData }) { const p = data.payments.summary; return <Grid><section className="admin-cc__kpis admin-cc__kpis--compact"><Kpi label="Today's volume" value={formatAdminCurrency(p.today_volume, true)} note="Successful ledger value" icon={<WalletCards/>}/><Kpi label="Success rate" value={p.success_rate === null ? 'Unavailable' : `${p.success_rate}%`} note="Recorded transactions" icon={<CheckCircle2/>}/><Kpi label="Pending" value={String(p.pending)} note="Needs provider result" icon={<ListChecks/>}/><Kpi label="Failed" value={String(p.failed)} note="Needs investigation" icon={<AlertTriangle/>}/></section><Panel wide title="Recent payment ledger"><DataTable columns={['Reference', 'State', 'Amount', 'Recorded']} rows={data.payments.recent.map(row => [row.transaction_reference, <Status value={row.status}/>, `${row.currency || 'MWK'} ${Number(row.amount).toLocaleString()}`, when(row.created_at)])}/><div className="admin-cc__panel-actions"><a href={data.links.payments}>Open payment and reconciliation tools</a></div></Panel><CapabilityPanel enabled={false} icon={<Activity/>} title="Provider probe not instrumented" body="A configured payment ledger does not prove PayChangu availability or webhook health. The console will show provider status only after a signed health/reconciliation probe exists."/></Grid>; }
function TieOperations({ data }: { data: AdminData }) { const t = data.telemetry; return <Grid><section className="admin-cc__kpis admin-cc__kpis--compact"><Kpi label="AI requests today" value={t.ai_requests_today === null ? 'Not recorded' : String(t.ai_requests_today)} note="Instrumented responses" icon={<Bot/>}/><Kpi label="Average latency" value={t.ai_latency_ms === null ? 'Not recorded' : `${Math.round(t.ai_latency_ms)} ms`} note="Provider round-trip" icon={<Activity/>}/><Kpi label="Provider failures" value={t.provider_failures_today === null ? 'Not recorded' : String(t.provider_failures_today)} note="Today" icon={<AlertTriangle/>}/><Kpi label="Tokens" value={t.input_tokens_today === null ? 'Not recorded' : String(t.input_tokens_today + (t.output_tokens_today || 0))} note="Input + output" icon={<Sparkles/>}/></section><Panel title="AI provider boundary"><div className="admin-cc__provider"><i className={t.configuration.tie_enabled ? 'is-enabled' : ''}/><div><b>{t.configuration.ai_provider || 'Provider not configured'}</b><span>{t.configuration.ai_model || 'Model not configured'}</span><small>{t.recording ? 'Telemetry recording is available.' : 'AI telemetry tables are not installed; cost and quality cannot be verified.'}</small></div></div></Panel><Panel title="Request tracing"><Capability enabled={t.trace_recording} label={t.trace_recording ? 'Trace recording available' : 'Trace recording unavailable'} detail="Request IDs can be inspected only after the deterministic pipeline records stage-level traces."/></Panel></Grid>; }
function Notifications({ data }: { data: AdminData }) { return <Grid><Panel title="Notification storage"><div className="admin-cc__big-number"><b>{data.notifications.stored.toLocaleString()}</b><span>stored notifications</span><small>{data.notifications.unread.toLocaleString()} currently unread</small></div></Panel><CapabilityPanel enabled={data.notifications.delivery_instrumented} icon={<Bell/>} title="Delivery operations" body="Email, SMS and push delivery rates are not instrumented. Stored in-app notifications must not be presented as successfully delivered external messages." href={data.links.notifications}/></Grid>; }
function Security({ data }: { data: AdminData }) { return <Grid><Panel wide title="Recent administrative audit"><DataTable columns={['Administrator', 'Role', 'Action', 'Details', 'Recorded']} rows={data.audit.map(row => [row.user_name || 'System', row.user_role || 'System', row.action, row.details, when(row.created_at)])}/><div className="admin-cc__panel-actions"><a href={data.links.security}>Open security center</a><a href={data.links.admin_users}>Manage admin roles</a></div></Panel></Grid>; }
function Support({ data }: { data: AdminData }) { return <Grid><Panel wide title="Open support cases"><DataTable columns={['Ticket', 'Subject', 'Category', 'Priority', 'Status', 'Created']} rows={data.support.map(row => [row.ticket_code, row.subject, titleCase(row.category), <Status value={row.priority}/>, <Status value={row.status}/>, when(row.created_at)])}/><div className="admin-cc__panel-actions"><a href={data.links.support}>Open support workspace</a></div></Panel></Grid>; }
function SettingsPanel({ data, boot }: { data: AdminData; boot: AdminBoot }) { return <Grid><Panel wide title="TIE feature flags"><div className="admin-cc__flags">{Object.entries(boot.features).map(([key, enabled]) => <article key={key}><div><b>{titleCase(key)}</b><small>Current authenticated frontend bootstrap state</small></div><Status value={enabled ? 'enabled' : 'disabled'}/></article>)}</div><div className="admin-cc__panel-actions"><a href={data.links.settings}>Open governed settings</a></div></Panel><CapabilityPanel enabled={false} icon={<Flag/>} title="Change controls" body="This React view is read-only. Dangerous feature, payment and security changes stay behind the audited PHP settings workflow and require explicit administrator action."/></Grid>; }
function Analytics({ data }: { data: AdminData }) { return <Grid><Panel title="Platform footprint"><div className="admin-cc__stat-list"><p><span>Customers</span><b>{data.metrics.customers.toLocaleString()}</b></p><p><span>Approved vendors</span><b>{data.metrics.vendors.toLocaleString()}</b></p><p><span>Listings</span><b>{data.inventory_quality.total.toLocaleString()}</b></p><p><span>Active bookings</span><b>{data.metrics.active_bookings.toLocaleString()}</b></p></div></Panel><Panel title="Revenue evidence"><div className="admin-cc__big-number"><b>{formatAdminCurrency(data.metrics.today_revenue)}</b><span>paid booking value today</span><small>Historical time-series aggregation is not instrumented in this contract.</small></div></Panel><Panel wide title="Catalogue mix"><Distribution data={data}/></Panel></Grid>; }
function SystemHealth({ data }: { data: AdminData }) { return <Grid><Panel wide title="Backend capability health"><HealthRows data={data}/></Panel><Panel title="Observability"><Capability enabled={data.capabilities.telemetry} label="Metrics" detail="TIE request and provider metric events"/><Capability enabled={data.capabilities.request_tracing} label="Request traces" detail="Cross-engine request stage records"/><Capability enabled={data.capabilities.payment_ledger} label="Payment ledger" detail="Recorded transaction or payment-intent state"/></Panel></Grid>; }
function ListingCategory({ data, type, title }: { data: AdminData; type: string; title: string }) { const row = data.listing_distribution.find(item => item.listing_type === type); return <Grid><Panel title={title}><div className="admin-cc__big-number"><b>{Number(row?.total || 0).toLocaleString()}</b><span>catalogue records</span><small>{Number(row?.active_count || 0).toLocaleString()} active</small></div></Panel><CapabilityPanel enabled={Boolean(row)} icon={<TicketCheck/>} title="Operational workspace" body="Use the authorised event workspace for approval, ticket capacity and check-in operations while the versioned admin event API is completed." href={data.links.events}/></Grid>; }

function Kpi({ label, value, note, icon }: { label: string; value: string; note: string; icon: any }) { return <article className="admin-cc__kpi"><header><span>{icon}</span><small>{label}</small></header><b>{value}</b><p>{note}</p></article>; }
function Panel({ title, action, className = '', wide = false, children }: { title: string; action?: any; className?: string; wide?: boolean; children: any }) { return <article className={`admin-cc__panel ${wide ? 'is-wide' : ''} ${className}`}><header><h2>{title}</h2>{action}</header>{children}</article>; }
function Grid({ children }: { children: any }) { return <section className="admin-cc__module-grid">{children}</section>; }
function Status({ value }: { value: string }) { const state = value.toLowerCase(); const tone = /approved|active|paid|success|enabled|healthy|confirmed|complete/.test(state) ? 'good' : /failed|rejected|suspended|invalid|critical|high/.test(state) ? 'danger' : /pending|warning|unknown|review|medium/.test(state) ? 'warn' : 'neutral'; return <span className={`admin-cc__status is-${tone}`}><i/>{titleCase(value)}</span>; }
function DataTable({ columns, rows }: { columns: string[]; rows: any[][] }) { return rows.length ? <div className="admin-cc__table"><div className="admin-cc__table-head">{columns.map(column => <b key={column}>{column}</b>)}</div>{rows.map((row, index) => <div className="admin-cc__table-row" key={index}>{row.map((cell, cellIndex) => <span key={cellIndex}>{cell}</span>)}</div>)}</div> : <div className="admin-cc__empty"><Database/><b>No authoritative records</b><p>This table is empty or the current role cannot access its records.</p></div>; }
function ActivityFeed({ rows }: { rows: ActivityRow[] }) { return rows.length ? <div className="admin-cc__feed">{rows.map((row, index) => <article key={`${row.at}-${index}`}><span>{row.type.slice(0, 1)}</span><p><b>{row.title}</b><small>{row.detail}</small></p><time>{when(row.at)}</time></article>)}</div> : <div className="admin-cc__empty"><Activity/><b>No recent activity</b><p>Instrumented bookings, payments and vendor changes will appear here.</p></div>; }
function Distribution({ data }: { data: AdminData }) { const max = Math.max(1, ...data.listing_distribution.map(item => Number(item.total))); return data.listing_distribution.length ? <div className="admin-cc__bars">{data.listing_distribution.map((row, index) => <article key={row.listing_type}><header><span>{titleCase(row.listing_type)}</span><b>{Number(row.total).toLocaleString()}</b></header><div><i style={{ width: `${(Number(row.total) / max) * 100}%` }} className={`tone-${index % 4}`}/></div><small>{Number(row.active_count).toLocaleString()} active</small></article>)}</div> : <div className="admin-cc__empty"><PackageSearch/><b>No marketplace catalogue</b><p>No listing distribution can be calculated.</p></div>; }
function HealthRows({ data }: { data: AdminData }) { return <div className="admin-cc__health-list">{data.health.map(row => <article key={row.name}><Status value={row.status}/><div><b>{row.name}</b><p>{row.detail}</p></div></article>)}</div>; }
function AttentionRows({ data }: { data: AdminData }) { const rows = data.attention.filter(row => row.count > 0); return rows.length ? <div className="admin-cc__attention-list">{rows.map(row => <article key={row.key}><span className={`is-${row.priority}`}>{row.count}</span><div><b>{row.label}</b><small>{titleCase(row.priority)} priority</small></div></article>)}</div> : <div className="admin-cc__empty"><CheckCircle2/><b>Queue clear</b><p>No currently instrumented action items.</p></div>; }
function Capability({ enabled, label, detail }: { enabled: boolean; label: string; detail: string }) { return <div className="admin-cc__capability"><span className={enabled ? 'is-enabled' : ''}>{enabled ? <CheckCircle2/> : <AlertTriangle/>}</span><div><b>{label}</b><p>{detail}</p></div></div>; }
function CapabilityPanel({ enabled, icon, title, body, href }: { enabled: boolean; icon: any; title: string; body: string; href?: string }) { return <Panel title={title}><div className="admin-cc__capability-card"><span className={enabled ? 'is-enabled' : ''}>{icon}</span><Status value={enabled ? 'available' : 'not connected'}/><p>{body}</p>{href && <a href={href}>Open authorised workspace <ChevronRight/></a>}</div></Panel>; }
