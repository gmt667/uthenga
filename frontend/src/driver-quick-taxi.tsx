import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle, BarChart3, Banknote, Bell, Car, CarTaxiFront, CalendarCheck, Check, CircleCheckBig, Circle, Copy,
  Clock, Download, Gauge, LayoutDashboard, LocateFixed, MapPin, MessageSquare, Phone, PhoneIncoming, PhoneOff, PhoneOutgoing, Plus, ReceiptText, RefreshCw, Route,
  Search, Settings as SettingsIcon, ShieldAlert, ShieldCheck, Users, WalletCards, Wifi, WifiOff, Wrench, X,
} from 'lucide-react';
import './driver-quick-taxi.css';

type Boot = { user?: { id: string; name: string; role: string }; csrf_token?: string; legacy_fallbacks: Record<string, string>; maps: { provider: string; enabled: boolean; browser_key: string } };

type Trip = {
  id: string; trip_code: string; status: string; bucket: string;
  passenger_name: string; passenger_phone: string | null;
  pickup_location: string; destination_location: string;
  vehicle_label: string | null; vehicle_plate: string | null;
  is_scheduled: boolean; scheduled_at: string | null;
  requested_at: string; assigned_at: string | null; accepted_at: string | null;
  en_route_at: string | null; arrived_at: string | null; onboard_at: string | null;
  started_at: string | null; completed_at: string | null; cancelled_at: string | null;
  cancellation_actor: string | null; cancellation_reason: string | null;
  estimated_fare: number | null; final_fare: number | null;
  distance_km: number | null; duration_seconds: number | null;
  payment_method: string | null; payment_status: string;
};
type TimelineEvent = { event_type: string; actor_type: string; previous_status: string | null; new_status: string | null; reason: string | null; created_at: string };
type TripDetail = { trip: Trip; timeline: TimelineEvent[] };
type DaySummary = { trips: number; completed: number; cancelled: number; earnings: number; distance_km: number };
type Dashboard = {
  is_online: boolean; online_since: string | null; active_trip: Trip | null;
  today: DaySummary; yesterday: DaySummary;
  readiness: { has_profile: boolean; is_verified: boolean };
  next_scheduled: Trip | null;
};
type TripList = { trips: Trip[]; counts: Record<string, number> };
type Passenger = {
  passenger_key: string; passenger_name: string; passenger_phone: string | null;
  trip_count: number; completed_count: number; cancelled_count: number;
  last_trip_at: string | null; status: 'active' | 'frequent' | 'previous';
  source?: 'trip' | 'coordination'; active_session_id?: string | null;
};
type PassengerList = { passengers: Passenger[]; counts: Record<string, number> };
type PassengerSummary = { today_passengers: number; repeat_passengers: number; active_passenger: number; total_served: number };
type PassengerTripSummary = { id: string; trip_code: string; status: string; requested_at: string; completed_at: string | null; pickup_location: string; destination_location: string; fare: number | null };
type PassengerNote = { id: number; author_id: string; body: string; created_at: string };
type PassengerDetail = { passenger: Passenger; current_trip: PassengerTripSummary | null; history: PassengerTripSummary[]; notes: PassengerNote[]; previous_issue: { trip_code: string; status: string; reason: string; occurred_at: string | null } | null };
type Conversation = { trip_id: string; trip_code: string; passenger_name: string; passenger_phone: string | null; summary: string; status: string; last_activity_at: string | null };
type TripNotification = { id: number; trip_id: string; trip_code: string; title: string; reason: string | null; created_at: string; is_read: boolean };
type CoordinationRun = {
  id: string; service_id: string; planned_departure_at: string; actual_departure_at: string | null;
  loading_started_at: string | null; travelling_started_at: string | null; completed_at: string | null;
  loading_location: string; driver_note: string | null; status: string; loading_status: string;
  remaining_seats: number; capacity: number;
};
type CoordinationQueueSession = {
  id: string; passenger_count: number; status: string; destination?: string | null; reservation_state?: string; customer_name?: string | null; booking_source?: string;
  expires_at?: string; accepted_at?: string | null; arrived_at?: string | null; boarded_at?: string | null; run?: CoordinationRun | null;
  location?: { latitude: number; longitude: number; accuracy_m: number | null; captured_at: string } | null; en_route_since?: string | null;
};
type CoordinationMessage = { id: string; sender_role: string; body: string; created_at: string };
type CallState = { state: 'NONE' | 'RINGING_OUT' | 'RINGING_IN' | 'ACCEPTED' | 'DECLINED' | 'CANCELLED' | 'ENDED'; call_request_id: string | null; peer_name: string | null };
type CallSignal = { id: number; sender_role: string; kind: 'offer' | 'answer' | 'ice' | 'hangup'; payload: any; created_at: string };
type CoordinationSessionDetail = { session: CoordinationQueueSession; viewer_role: 'customer' | 'vendor'; messages: CoordinationMessage[]; workspace: { allowed_actions: string[] }; call: CallState };
type DepartureQueue = { active_run: CoordinationRun | null; sessions: CoordinationQueueSession[]; call_requests: { id: string; session_id: string; expires_at: string }[] };
type PaymentLedgerEntry = { session_id: string; boarding_status: string; payment_state: string; amount: number | null; method: string | null };
type PaymentLedger = { paid_count: number; outstanding_count: number; outstanding_amount: number; cash_amount: number; digital_amount: number; entries: PaymentLedgerEntry[] };
type DepartureReadiness = { boarded: number; unresolved: number; paid_count: number; outstanding_count: number; outstanding_amount: number; ready: boolean };
type DriverSessionOption = { profile_id: string; service_id: string; seat_class_id: number; name: string; active: boolean; capacity: number; inventory_remaining: number; fare: number; origin: string; destination: string; loading_location: string; departure_time: string };
type EarningsSummary = { period: string; trips: number; cancelled: number; earnings: number; average_fare: number; distance_km: number; digital_count: number; cash_count: number };
type EarningsTrendPoint = { date: string; earnings: number; trips: number };
type EarningsTransaction = { id: string; trip_code: string; completed_at: string | null; route: string; fare: number; payment_method: string | null; source?: 'trip' | 'departure' };
type EarningsGoal = { weekly_goal: number | null; week_earnings: number };
type VehicleProfile = { make_model: string; plate_number: string; colour: string | null; category: string | null; photo_url: string | null; status: 'active' | 'inactive'; current_mileage_km: number | null; mileage_updated_at: string | null; assigned_at: string | null };
type VehicleDocument = { document_type: string; expiry_date: string; days_remaining: number; status: 'valid' | 'expiring_soon' | 'expired' };
type MaintenanceRecord = { id: number; service_type: string; serviced_at: string; mileage_km: number | null; notes: string | null };
type VehicleIssue = { id: number; category: string; description: string; severity: string; status: string; created_at: string; resolved_at: string | null };
type VehicleActivity = { today_distance_km: number; today_trips: number; average_trip_distance_km: number; current_session_minutes: number | null };
type VehicleOverview = { vehicle: VehicleProfile | null; documents: VehicleDocument[]; maintenance: MaintenanceRecord[]; open_issues: VehicleIssue[]; resolved_issues: VehicleIssue[]; activity: VehicleActivity; mileage_since_service: number | null };
type ShiftSession = { started_at: string; elapsed_minutes: number; trips_count: number; earnings: number };
type ShiftHistoryEntry = { id: number; started_at: string; ended_at: string | null; duration_minutes: number; trips_count: number; earnings: number };
type AvailabilityDay = { day_of_week: number; label: string; is_off: boolean; start_time: string | null; end_time: string | null };
type NextAvailable = { date: string; label: string; start_time: string; end_time: string };
type ScheduleOverview = { current_session: ShiftSession | null; history: ShiftHistoryEntry[]; availability: AvailabilityDay[]; next_available: NextAvailable | null };
type ReportSummary = { trips?: number; completed?: number; cancelled?: number; disputed?: number; completion_rate?: number | null; gross_earnings?: number; average_fare?: number; distance_km?: number; average_duration_seconds?: number; shifts?: number; online_minutes?: number; maintenance_events?: number; vehicle?: { make_model: string; plate_number: string; current_mileage_km: number | null } | null };
type TripReportRow = { id: string; trip_code: string; requested_at: string; route: string; distance_km: number | null; duration_seconds: number | null; status: string; fare: number | null; payment_method: string | null };
type EarningsReportRow = { id: string; trip_code: string; completed_at: string; route: string; fare: number; payment_method: string | null; source?: 'trip' | 'departure' };
type ShiftReportRow = { id: number; started_at: string; ended_at: string | null; duration_minutes: number | null; trips_count: number; earnings: number };
type VehicleReportRow = { id: number; service_type: string; serviced_at: string; mileage_km: number | null; notes: string | null };
type Report = { type: string; range: { start: string; end: string }; summary: ReportSummary; rows: any[] };
type DriverProfile = { name: string | null; email: string | null; phone: string | null; driver_code: string | null; license_number: string | null; is_verified: boolean; rating_average: number | null; rating_count: number | null; total_trips: number | null; has_driver_profile: boolean };
type DriverPreferences = { notification_sound: boolean; emergency_contact_name: string | null; emergency_contact_phone: string | null };
type DeactivationRequest = { requested_at: string; reason: string | null };
type DriverSettingsOverview = { profile: DriverProfile; preferences: DriverPreferences; deactivation: DeactivationRequest | null };

function apiUrl(path: string): string {
  const configured = (window as any).UTHENGA_BASE_URL as string | undefined;
  const base = (configured || '/uthenga/').replace(/\/$/, '');
  return `${base}/api/tie/${path}`;
}
function driverBaseUrl(): string {
  const configured = (window as any).UTHENGA_BASE_URL as string | undefined;
  return (configured || '/uthenga/').replace(/\/?$/, '/');
}
async function apiGet<T>(path: string, params?: Record<string, string | undefined>): Promise<T> {
  const usp = new URLSearchParams();
  Object.entries(params || {}).forEach(([key, value]) => { if (value) usp.set(key, value); });
  const query = usp.toString();
  const response = await fetch(apiUrl(path) + (query ? `?${query}` : ''), { credentials: 'include' });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) throw new Error(data?.error?.message || 'Uthenga could not complete that request.');
  return data as T;
}
async function apiPost<T>(path: string, body: unknown, csrf?: string): Promise<T> {
  const response = await fetch(apiUrl(path), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) }, body: JSON.stringify(body) });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) throw new Error(data?.error?.message || 'Uthenga could not complete that request.');
  return data as T;
}

const money = (value: number | null | undefined) => value === null || value === undefined ? '—' : `MK ${Number(value).toLocaleString()}`;
const timeOf = (iso: string | null) => iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
const dateTimeOf = (iso: string | null) => iso ? new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—';
// Plain 'YYYY-MM-DD' values (MySQL DATE columns) must be parsed as local
// midnight, not UTC midnight, or a negative-UTC-offset browser shows the
// previous day.
const dateOnly = (value: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString([], { dateStyle: 'medium' }) : '—';

const STATUS_META: Record<string, { label: string; color: string }> = {
  REQUESTED: { label: 'Requested', color: '#f59e0b' },
  ASSIGNED: { label: 'Assigned', color: '#f59e0b' },
  ACCEPTED: { label: 'Accepted', color: '#3b82f6' },
  EN_ROUTE: { label: 'En Route', color: '#ef4444' },
  ARRIVED: { label: 'Arrived', color: '#ef4444' },
  ONBOARD: { label: 'Passenger Onboard', color: '#ef4444' },
  IN_PROGRESS: { label: 'In Progress', color: '#ef4444' },
  COMPLETED: { label: 'Completed', color: '#10b981' },
  CANCELLED: { label: 'Cancelled', color: '#94a3b8' },
  NO_SHOW: { label: 'No-show', color: '#94a3b8' },
  DISPUTED: { label: 'Disputed', color: '#a855f7' },
};
function StatusChip({ status }: { status: string }) {
  const meta = STATUS_META[status] || { label: status, color: '#94a3b8' };
  return <span className="dqx-chip" style={{ background: meta.color + '22', color: meta.color }}><i style={{ background: meta.color }} />{meta.label}</span>;
}

// tel: is the correct, standard way to place a call from a web page — on a
// real phone it opens the native dialer directly, no prompt. It only shows
// an OS-level chooser on a desktop whose tel: handler is misconfigured (e.g.
// Linux + KDE Connect with no phone paired) — that's an environment quirk,
// not an app bug, so the call action stays real; copying the number is only
// the fallback for exactly that situation, and for reaching a legacy
// manually-entered passenger who has no in-app calling channel at all.
function PhoneContactActions({ phone, label, style }: { phone: string; label?: string; style?: React.CSSProperties }) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try { await navigator.clipboard.writeText(phone); setCopied(true); window.setTimeout(() => setCopied(false), 2000); }
    catch { /* clipboard unavailable; the number is still visible either way */ }
  };
  return <div className="dqx-phone-actions" style={style}>
    <a className="dqx-btn dqx-btn--ghost" href={`tel:${phone}`}><Phone /> {label ? `${label}: ${phone}` : `Call ${phone}`}</a>
    <button type="button" className="dqx-btn dqx-btn--ghost dqx-btn--icon" title="Copy number" onClick={() => void copy()}>{copied ? <Check size={14} /> : <Copy size={14} />}</button>
  </div>;
}

const PASSENGER_STATUS_META: Record<string, { label: string; color: string }> = {
  active: { label: 'Active', color: '#ef4444' },
  frequent: { label: 'Frequent', color: '#3b82f6' },
  previous: { label: 'Previous', color: '#94a3b8' },
};
function PassengerStatusChip({ status }: { status: string }) {
  const meta = PASSENGER_STATUS_META[status] || { label: status, color: '#94a3b8' };
  return <span className="dqx-chip" style={{ background: meta.color + '22', color: meta.color }}><i style={{ background: meta.color }} />{meta.label}</span>;
}

const ACTIVE_NEXT: Record<string, { target: string; label: string }> = {
  ACCEPTED: { target: 'EN_ROUTE', label: 'Start Navigating' },
  EN_ROUTE: { target: 'ARRIVED', label: 'Arrived' },
  ARRIVED: { target: 'ONBOARD', label: 'Passenger Onboard' },
  ONBOARD: { target: 'IN_PROGRESS', label: 'Start Trip' },
};
const CANCELLABLE_STATUSES = ['REQUESTED', 'ASSIGNED', 'ACCEPTED', 'EN_ROUTE', 'ARRIVED'];

type TabId = 'dashboard' | 'trips' | 'passengers' | 'messages' | 'earnings' | 'vehicle' | 'schedule' | 'reports' | 'settings';
const TABS: { id: TabId; label: string; icon: any }[] = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'trips', label: 'Trips', icon: Route },
  { id: 'passengers', label: 'Passengers', icon: Users },
  { id: 'messages', label: 'Messages', icon: MessageSquare },
  { id: 'earnings', label: 'Earnings', icon: WalletCards },
  { id: 'vehicle', label: 'Vehicle', icon: Car },
  { id: 'schedule', label: 'Schedule', icon: CalendarCheck },
  { id: 'reports', label: 'Reports', icon: BarChart3 },
  { id: 'settings', label: 'Settings', icon: SettingsIcon },
];

export function DriverQuickTaxi({ boot }: { boot: Boot }) {
  const [tab, setTab] = useState<TabId>('dashboard');
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [connected, setConnected] = useState(true);
  const [lastSync, setLastSync] = useState<Date | null>(null);
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const [completion, setCompletion] = useState<{ trip: Trip; result: Trip | null } | null>(null);
  const [selectedTripId, setSelectedTripId] = useState('');
  const [unreadMessages, setUnreadMessages] = useState(0);

  const loadDashboard = () => apiGet<{ dashboard: Dashboard }>('trip/dashboard.php')
    .then(response => { setDashboard(response.dashboard); setConnected(true); setLastSync(new Date()); })
    .catch(error => { setConnected(false); setNotice(error instanceof Error ? error.message : 'Uthenga could not reach the trip engine.'); });

  useEffect(() => { void loadDashboard(); const interval = window.setInterval(loadDashboard, 10_000); return () => window.clearInterval(interval); }, []);

  useEffect(() => {
    const load = () => apiGet<{ notifications: { unread_count: number } }>('message/notifications.php').then(response => setUnreadMessages(response.notifications.unread_count)).catch(() => undefined);
    void load(); const interval = window.setInterval(load, 15_000); return () => window.clearInterval(interval);
  }, [refreshKey]);

  const act = async (payload: Record<string, unknown>): Promise<any> => {
    setBusy(true); setNotice('');
    try {
      const response = await apiPost<{ result: any }>('trip/action.php', payload, boot.csrf_token);
      await loadDashboard(); setRefreshKey(key => key + 1);
      return response.result;
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Uthenga could not complete that action.'); throw error; }
    finally { setBusy(false); }
  };

  const scheduleAct = async (payload: Record<string, unknown>): Promise<any> => {
    setBusy(true); setNotice('');
    try {
      const response = await apiPost<{ result: any }>('schedule/action.php', payload, boot.csrf_token);
      await loadDashboard(); setRefreshKey(key => key + 1);
      return response.result;
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Uthenga could not complete that action.'); throw error; }
    finally { setBusy(false); }
  };

  const openComplete = (trip: Trip) => setCompletion({ trip, result: null });
  const confirmComplete = async (input: Record<string, unknown>) => {
    if (!completion) return;
    const detail = await act({ action: 'complete', trip_id: completion.trip.id, ...input }) as TripDetail;
    setCompletion({ trip: completion.trip, result: detail.trip });
  };

  const firstName = boot.user?.name?.split(' ')[0] || 'Driver';

  return <main className="dqx">
    <aside className="dqx__rail">
      <div className="dqx__brand"><span className="dqx__brandmark"><CarTaxiFront /></span><div><b>UTHENGA</b><small>DRIVER OPS · QUICK TAXI</small></div></div>
      <div className="dqx__profile"><span>{firstName[0]?.toUpperCase() || 'D'}</span><div><b>{boot.user?.name || 'Driver'}</b><small>Quick Taxi Driver</small></div></div>
      <nav>
        {TABS.map(item => { const Icon = item.icon; return <button key={item.id} className={tab === item.id ? 'is-active' : ''} onClick={() => setTab(item.id)}><Icon /><span>{item.label}</span>{item.id === 'messages' && unreadMessages > 0 && <em>{unreadMessages}</em>}</button>; })}
      </nav>
      <div className="dqx__sync"><span className={connected ? 'is-ok' : 'is-down'}>{connected ? <Wifi /> : <WifiOff />}{connected ? 'Live Sync Connected' : 'Reconnecting…'}</span>{lastSync && <small>Updated {lastSync.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</small>}</div>
      {boot.legacy_fallbacks?.vendor_workspace && <a className="dqx__back" href={boot.legacy_fallbacks.vendor_workspace}>← Vendor workspace</a>}
    </aside>

    <section className="dqx__main">
      {notice && <div className="dqx-banner">{notice}<button onClick={() => setNotice('')}><X /></button></div>}
      {tab === 'dashboard' && <DashboardWorkspace boot={boot} dashboard={dashboard} busy={busy} unreadMessages={unreadMessages} act={act} scheduleAct={scheduleAct} onOpenComplete={openComplete} onNavigate={setTab} firstName={firstName} refreshKey={refreshKey} />}
      {tab === 'trips' && <TripsWorkspace boot={boot} today={dashboard?.today || null} onSelectTrip={setSelectedTripId} refreshKey={refreshKey} />}
      {tab === 'passengers' && <PassengersWorkspace boot={boot} onSelectTrip={setSelectedTripId} refreshKey={refreshKey} />}
      {tab === 'messages' && <MessagesWorkspace boot={boot} onSelectTrip={setSelectedTripId} refreshKey={refreshKey} hasActiveTrip={Boolean(dashboard?.active_trip)} />}
      {tab === 'earnings' && <EarningsWorkspace boot={boot} onSelectTrip={setSelectedTripId} refreshKey={refreshKey} />}
      {tab === 'vehicle' && <VehicleWorkspace boot={boot} refreshKey={refreshKey} />}
      {tab === 'schedule' && <ScheduleWorkspace scheduleAct={scheduleAct} refreshKey={refreshKey} />}
      {tab === 'reports' && <ReportsWorkspace />}
      {tab === 'settings' && <SettingsWorkspace boot={boot} />}
    </section>

    {selectedTripId && <TripDrawer tripId={selectedTripId} busy={busy} act={act} onOpenComplete={openComplete} onClose={() => setSelectedTripId('')} />}
    {completion && <CompletionFlow trip={completion.trip} result={completion.result} busy={busy} onConfirm={confirmComplete} onClose={() => setCompletion(null)} />}
  </main>;
}

function timeOfDayGreeting(): string {
  const hour = new Date().getHours();
  return hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';
}
function isoWeekday(date: Date): number { const day = date.getDay(); return day === 0 ? 7 : day; }
function pctDelta(current: number, previous: number): string | null {
  if (previous <= 0) return current > 0 ? 'New today' : null;
  const rounded = Math.round(((current - previous) / previous) * 100);
  if (rounded === 0) return 'Same as yesterday';
  return `${rounded > 0 ? '+' : ''}${rounded}% vs yesterday`;
}
function numDelta(current: number, previous: number, unit = ''): string | null {
  const diff = Math.round((current - previous) * 10) / 10;
  if (diff === 0) return 'Same as yesterday';
  return `${diff > 0 ? '+' : ''}${Number.isInteger(diff) ? diff : diff.toFixed(1)}${unit} vs yesterday`;
}
// Deterministic, state-driven guidance — never an LLM call, never a fact the
// backend hasn't already computed. Mirrors assistantMessage() below, extended
// to the cockpit's LOADING/TRAVELING modes so "AI provides contextual
// guidance" never risks inventing an operational fact.
const LONG_WALK_MINUTES = 12;

function loadingGuidance(sessions: CoordinationQueueSession[], readiness: DepartureReadiness | null): string {
  const pending = sessions.filter(session => session.status === 'PENDING_VENDOR').length;
  if (pending > 0) return `${pending} new passenger request${pending === 1 ? '' : 's'} waiting for your decision.`;
  // A passenger walking unusually long deserves a specific, actionable
  // callout — derived from the real EN_ROUTE timestamp, never guessed.
  const longWalkers = sessions.filter(session => session.status === 'CUSTOMER_EN_ROUTE' && session.en_route_since && (Date.now() - new Date(session.en_route_since).getTime()) / 60_000 > LONG_WALK_MINUTES);
  if (longWalkers.length > 0) { const minutes = Math.round((Date.now() - new Date(longWalkers[0].en_route_since!).getTime()) / 60_000); return `${longWalkers[0].customer_name || 'A passenger'} has been walking for ${minutes} min — consider checking in.`; }
  const unresolved = sessions.filter(session => ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED'].includes(session.status)).length;
  if (unresolved > 0) return `${unresolved} confirmed passenger${unresolved === 1 ? '' : 's'} ${unresolved === 1 ? "hasn't" : "haven't"} boarded yet.`;
  if (readiness && readiness.outstanding_count > 0) return `${readiness.outstanding_count} boarded passenger${readiness.outstanding_count === 1 ? '' : 's'} still ${readiness.outstanding_count === 1 ? 'needs' : 'need'} to pay before departure.`;
  if (readiness?.ready) return 'All passengers are boarded and paid. Ready to depart.';
  return 'Waiting for passengers to arrive.';
}
function travelingGuidance(run: CoordinationRun, ledger: PaymentLedger | null): string {
  const elapsedMinutes = run.travelling_started_at ? Math.max(0, Math.round((Date.now() - new Date(run.travelling_started_at).getTime()) / 60_000)) : 0;
  if (ledger && ledger.outstanding_count > 0) return `${ledger.outstanding_count} passenger payment${ledger.outstanding_count === 1 ? '' : 's'} still outstanding mid-trip.`;
  if (elapsedMinutes > 0) return `You've been travelling for ${elapsedMinutes}m. Drive safely.`;
  return 'Trip under way. Drive safely.';
}

function assistantMessage(dashboard: Dashboard): string {
  if (dashboard.active_trip) return `You're on a trip with ${dashboard.active_trip.passenger_name}. Drive safely.`;
  if (!dashboard.readiness.has_profile || !dashboard.readiness.is_verified) return 'Complete your driver verification to unlock the full Quick Taxi console.';
  if (dashboard.next_scheduled) return `You have a scheduled trip at ${timeOf(dashboard.next_scheduled.scheduled_at)} with ${dashboard.next_scheduled.passenger_name}. Get ready.`;
  if (dashboard.is_online) return "You're on shift — open a departure from the Trips tab when you're ready to load passengers.";
  return "Start your shift when you're ready to open a departure and load passengers.";
}
type GoogleMaps = any;
declare global {
  interface Window {
    google?: { maps?: GoogleMaps };
    __uthengaGoogleMapsLoader?: Promise<GoogleMaps>;
    __uthengaGoogleMapsReady?: () => void;
  }
}
function loadGoogleMaps(browserKey: string): Promise<GoogleMaps> {
  if (window.google?.maps) return Promise.resolve(window.google.maps);
  if (window.__uthengaGoogleMapsLoader) return window.__uthengaGoogleMapsLoader;
  window.__uthengaGoogleMapsLoader = new Promise((resolve, reject) => {
    const callback = '__uthengaGoogleMapsReady';
    const timeout = window.setTimeout(() => reject(new Error('Google Maps did not respond in time.')), 15_000);
    window.__uthengaGoogleMapsReady = () => { window.clearTimeout(timeout); resolve(window.google?.maps); };
    const script = document.createElement('script');
    script.async = true; script.defer = true;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(browserKey)}&v=weekly&callback=${callback}`;
    script.onerror = () => { window.clearTimeout(timeout); reject(new Error('Google Maps could not be loaded. Check the browser-key restrictions.')); };
    document.head.appendChild(script);
  });
  return window.__uthengaGoogleMapsLoader;
}
const googleDarkMapStyle = [
  { elementType: 'geometry', stylers: [{ color: '#0a1727' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#a9c8d9' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#081220' }] },
  { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#24465f' }] },
  { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#0d2335' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#103a39' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#18344b' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#24536e' }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9cc6d4' }] },
  { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#132c43' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#071524' }] },
];

type DriverGeoState = 'idle' | 'requesting' | 'granted' | 'denied' | 'unsupported';
const DRIVER_LOW_ACCURACY_THRESHOLD_M = 50;

type DriverMapPassenger = { id: string; name: string; latitude: number; longitude: number };

function DriverLiveMap({ mapsConfig, passengers = [] }: { mapsConfig: Boot['maps']; passengers?: DriverMapPassenger[] }) {
  const container = useRef<HTMLDivElement | null>(null);
  const map = useRef<any>(null);
  const maps = useRef<GoogleMaps | null>(null);
  const marker = useRef<any>(null);
  const passengerMarkers = useRef<Map<string, any>>(new Map());
  const watchId = useRef<number | null>(null);
  const recenterControl = useRef<HTMLButtonElement | null>(null);
  const latestPosition = useRef<{ latitude: number; longitude: number; accuracy: number } | null>(null);
  const [mapLoadError, setMapLoadError] = useState('');
  const [geoState, setGeoState] = useState<DriverGeoState>('idle');
  const [geoError, setGeoError] = useState('');
  const [position, setPosition] = useState<{ latitude: number; longitude: number; accuracy: number } | null>(null);
  latestPosition.current = position;

  const requestLocation = () => {
    if (!navigator.geolocation) { setGeoState('unsupported'); return; }
    setGeoState('requesting'); setGeoError('');
    if (watchId.current !== null) navigator.geolocation.clearWatch(watchId.current);
    watchId.current = navigator.geolocation.watchPosition(
      pos => { setGeoState('granted'); setPosition({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy }); },
      error => { setGeoState('denied'); setGeoError(error.code === error.PERMISSION_DENIED ? 'Location access was denied. Enable it for this site in your browser settings, then try again.' : 'Your device could not determine its location. Check GPS/network and try again.'); },
      // maximumAge: 0 forces a fresh GPS read on every update — never a
      // stale cached fix — since accurate live tracking while driving
      // matters more here than battery/network overhead.
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20_000 },
    );
  };

  useEffect(() => {
    requestLocation();
    return () => { if (watchId.current !== null) navigator.geolocation.clearWatch(watchId.current); };
  }, []);

  useEffect(() => {
    if (!container.current || map.current || !mapsConfig.enabled || !mapsConfig.browser_key || !position) return;
    let disposed = false;
    setMapLoadError('');
    void loadGoogleMaps(mapsConfig.browser_key).then(googleMaps => {
      if (disposed || !container.current) return;
      maps.current = googleMaps;
      const center = { lat: position.latitude, lng: position.longitude };
      const instance = new googleMaps.Map(container.current, {
        center, zoom: 14, styles: googleDarkMapStyle, disableDefaultUI: true,
        zoomControl: true, zoomControlOptions: { position: googleMaps.ControlPosition.RIGHT_BOTTOM },
        mapTypeControl: true, mapTypeControlOptions: { position: googleMaps.ControlPosition.TOP_RIGHT, style: googleMaps.MapTypeControlStyle.HORIZONTAL_BAR },
        gestureHandling: 'greedy', clickableIcons: false, backgroundColor: '#07182a',
      });
      map.current = instance;
      const control = document.createElement('button');
      control.type = 'button'; control.className = 'uthenga-map-locate-control';
      control.title = 'Recenter on my location'; control.setAttribute('aria-label', 'Recenter on my location'); control.textContent = '⌖';
      control.addEventListener('click', event => {
        event.preventDefault(); event.stopPropagation();
        const current = latestPosition.current; if (!current) return;
        instance.panTo({ lat: current.latitude, lng: current.longitude }); instance.setZoom(15);
      });
      instance.controls[googleMaps.ControlPosition.RIGHT_BOTTOM].push(control); recenterControl.current = control;
    }).catch((error: unknown) => { if (!disposed) setMapLoadError(error instanceof Error ? error.message : 'Google Maps could not be loaded.'); });
    return () => { disposed = true; recenterControl.current?.remove(); marker.current?.setMap(null); passengerMarkers.current.forEach(m => m.setMap(null)); passengerMarkers.current.clear(); map.current = null; maps.current = null; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mapsConfig.browser_key, mapsConfig.enabled, Boolean(position)]);

  useEffect(() => {
    if (!map.current || !maps.current || !position) return;
    const point = { lat: position.latitude, lng: position.longitude };
    marker.current?.setMap(null);
    marker.current = new maps.current.Marker({ position: point, map: map.current, title: 'Your live location', icon: { path: maps.current.SymbolPath.CIRCLE, scale: 9, fillColor: '#31e99d', fillOpacity: 1, strokeColor: '#e6ffff', strokeWeight: 3 } });
    map.current.panTo(point);
  }, [position]);

  // Every passenger who has shared a location gets a distinct marker — the
  // driver's "all passengers on one map" view. Markers are reconciled by id
  // rather than cleared-and-rebuilt so they don't flicker on every poll.
  useEffect(() => {
    if (!map.current || !maps.current) return;
    const seen = new Set<string>();
    passengers.forEach(passenger => {
      seen.add(passenger.id);
      const point = { lat: passenger.latitude, lng: passenger.longitude };
      const existing = passengerMarkers.current.get(passenger.id);
      if (existing) { existing.setPosition(point); }
      else {
        const created = new maps.current.Marker({ position: point, map: map.current, title: passenger.name, label: { text: '●', color: '#062315', fontSize: '10px' }, icon: { path: maps.current.SymbolPath.CIRCLE, scale: 10, fillColor: '#f59e0b', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 } });
        passengerMarkers.current.set(passenger.id, created);
      }
    });
    passengerMarkers.current.forEach((markerInstance, id) => { if (!seen.has(id)) { markerInstance.setMap(null); passengerMarkers.current.delete(id); } });
  }, [passengers]);

  if (!mapsConfig.enabled || !mapsConfig.browser_key) return <div className="dqx-map-gate"><LocateFixed /><b>Google Maps is not configured</b><p>Ask an administrator to add the Google Maps browser key to enable the live map.</p></div>;

  if (geoState !== 'granted') {
    return <div className="dqx-map-gate">
      <LocateFixed />
      <b>Location access is required</b>
      <p>{geoState === 'unsupported' ? 'This device or browser cannot share its location.' : geoState === 'denied' ? geoError : 'Uthenga needs your live location to show you on the map while you drive.'}</p>
      {geoState !== 'unsupported' && <button className="dqx-btn dqx-btn--primary dqx-btn--sm" onClick={requestLocation} disabled={geoState === 'requesting'}>{geoState === 'requesting' ? 'Requesting…' : 'Try Again'}</button>}
    </div>;
  }

  const lowAccuracy = position !== null && position.accuracy > DRIVER_LOW_ACCURACY_THRESHOLD_M;
  return <>
    <div className="dqx-map-panel__live" ref={container} />
    {mapLoadError && <div className="dqx-map-gate dqx-map-gate--overlay"><b>Map unavailable</b><p>{mapLoadError}</p></div>}
    {position && <div className={`dqx-map-panel__accuracy ${lowAccuracy ? 'is-warn' : ''}`}>{lowAccuracy && <AlertTriangle />}Accuracy ±{Math.round(position.accuracy)} m</div>}
    {passengers.length > 0 && <div className="dqx-map-panel__passenger-count">{passengers.length} passenger{passengers.length === 1 ? '' : 's'} sharing location</div>}
  </>;
}

function StatCard({ icon, tone, label, value, note }: { icon: any; tone: string; label: string; value: string; note?: string | null }) {
  return <article className={`dqx-stat-card is-${tone}`}>
    <div className="dqx-stat-card__icon">{icon}</div>
    <div className="dqx-stat-card__body"><b>{value}</b><span>{label}</span>{note && <em>{note}</em>}</div>
  </article>;
}

function DashboardWorkspace({ boot, dashboard, busy, act, scheduleAct, onOpenComplete, onNavigate, firstName, unreadMessages, refreshKey }: {
  boot: Boot; dashboard: Dashboard | null; busy: boolean; act: (payload: Record<string, unknown>) => Promise<any>; scheduleAct: (payload: Record<string, unknown>) => Promise<any>;
  onOpenComplete: (trip: Trip) => void; onNavigate: (tab: TabId) => void; firstName: string; unreadMessages: number; refreshKey: number;
}) {
  const [goal, setGoal] = useState<EarningsGoal | null>(null);
  const [schedule, setSchedule] = useState<ScheduleOverview | null>(null);
  const [vehicle, setVehicle] = useState<VehicleOverview | null>(null);
  const [queue, setQueue] = useState<DepartureQueue | null>(null);

  useEffect(() => {
    apiGet<{ goal: EarningsGoal }>('earnings/goal.php').then(response => setGoal(response.goal)).catch(() => undefined);
    apiGet<{ overview: ScheduleOverview }>('schedule/overview.php').then(response => setSchedule(response.overview)).catch(() => undefined);
    apiGet<{ overview: VehicleOverview }>('vehicle/overview.php').then(response => setVehicle(response.overview)).catch(() => undefined);
  }, [refreshKey]);

  // A departure summary only — configuring and running a departure (adding,
  // accepting, and monitoring the several passengers who share one Quick Taxi)
  // lives on the Trips tab now, not here. See TripsWorkspace/DepartureCockpit.
  useEffect(() => {
    const loadQueue = () => apiGet<{ coordination: DepartureQueue }>('coordination/vendor-queue.php').then(response => setQueue(response.coordination)).catch(() => undefined);
    void loadQueue(); const interval = window.setInterval(loadQueue, 5_000); return () => window.clearInterval(interval);
  }, [refreshKey]);

  if (!dashboard) return <div className="dqx-loading"><RefreshCw className="dqx-spin" /><p>Opening your Quick Taxi console…</p></div>;

  const { active_trip, is_online, today, yesterday, readiness, next_scheduled } = dashboard;

  const completionDenominator = today.completed + today.cancelled;
  const completionRate = completionDenominator > 0 ? Math.round((today.completed / completionDenominator) * 100) : null;
  const avgFare = today.completed > 0 ? today.earnings / today.completed : 0;
  const goalProgress = goal?.weekly_goal ? Math.min(100, (goal.week_earnings / goal.weekly_goal) * 100) : 0;
  const route = active_trip || next_scheduled;

  const vehicleDocStatus = !vehicle?.documents.length ? null : vehicle.documents.some(document => document.status === 'expired') ? 'expired' : vehicle.documents.some(document => document.status === 'expiring_soon') ? 'expiring_soon' : 'valid';
  const alerts: { tone: 'danger' | 'warn'; title: string; detail: string }[] = [
    ...(!readiness.has_profile || !readiness.is_verified ? [{ tone: 'warn' as const, title: 'Driver profile not verified', detail: 'Complete verification in Settings to unlock the full console.' }] : []),
    ...(vehicle?.documents || []).filter(document => document.status !== 'valid').map(document => ({ tone: (document.status === 'expired' ? 'danger' : 'warn') as 'danger' | 'warn', title: `${VEHICLE_DOCUMENT_LABELS[document.document_type] || document.document_type} ${document.status === 'expired' ? 'has expired' : 'expires soon'}`, detail: `${document.status === 'expired' ? 'Expired' : 'Expires'} ${dateOnly(document.expiry_date)}.` })),
    ...(vehicle?.open_issues || []).map(issue => ({ tone: (issue.severity === 'critical' ? 'danger' : 'warn') as 'danger' | 'warn', title: `Open vehicle issue: ${titleCase(issue.category)}`, detail: issue.description })),
  ];

  const todayAvailability = schedule?.availability.find(day => day.day_of_week === isoWeekday(new Date()));

  return <div className="dqx-dashboard dqx-dashboard--rich">
    <div className="dqx-dashboard__main">
      <header className="dqx-dash-topbar">
        <div>
          <h1>Good {timeOfDayGreeting()}, {firstName} <span>👋</span></h1>
          <p>{active_trip ? 'You have an active trip in progress.' : is_online ? "You're on shift and ready to open a departure." : 'You are off shift. Start your shift when you\'re ready to drive.'} <span className={`dqx-status-pill ${is_online ? 'is-online' : ''}`}>{is_online ? 'On Shift' : 'Off Shift'}</span></p>
        </div>
        <div className="dqx-dash-topbar__meta">
          <span className="dqx-meta-chip">🌤 26°C</span>
          <span className="dqx-meta-chip"><MapPin />Lilongwe, Malawi</span>
          <button className="dqx-bell-btn" onClick={() => onNavigate('messages')}><Bell />{unreadMessages > 0 && <em>{unreadMessages}</em>}</button>
        </div>
      </header>

      {queue?.active_run ? <ActiveDeparturePanel run={queue.active_run} sessions={queue.sessions} onManage={() => onNavigate('trips')} /> : active_trip ? <ActiveTripPanel trip={active_trip} busy={busy} act={act} onOpenComplete={onOpenComplete} /> : <article className={`dqx-hero dqx-hero--rich ${is_online ? 'is-online' : 'is-offline'}`}>
        <div className="dqx-hero__visual"><CarTaxiFront /></div>
        <div className="dqx-hero__main">
          <span className="dqx-hero__badge">{is_online ? '● YOU ARE ONLINE' : '● OFFLINE'}</span>
          <h2>{is_online ? "You're on shift" : 'Ready to drive?'}</h2>
          <p>{is_online ? 'Open a departure from the Trips tab whenever you\'re ready to start loading passengers.' : 'Start your shift, then open a departure when you\'re ready to load passengers.'}</p>
          <div className="dqx-hero__metrics">
            <div><small>Online Time</small><b>{schedule?.current_session ? durationLabel(schedule.current_session.elapsed_minutes) : '—'}</b></div>
            <div><small>Completion Rate</small><b>{completionRate !== null ? `${completionRate}%` : '—'}</b></div>
            <div><small>Avg Fare</small><b>{today.completed > 0 ? money(avgFare) : '—'}</b></div>
          </div>
          <div className="dqx-hero__actions">
            <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void scheduleAct({ action: is_online ? 'end_shift' : 'start_shift' })}>{is_online ? 'End Shift' : 'Start Shift'}</button>
            <button className="dqx-btn dqx-btn--ghost" onClick={() => onNavigate('trips')}><CarTaxiFront /> Open Departure</button>
          </div>
        </div>
        <div className="dqx-hero__goal">
          <small>THIS WEEK'S GOAL</small>
          {goal?.weekly_goal ? <>
            <b>{money(goal.weekly_goal)}</b>
            <div className="dqx-progress"><i style={{ width: `${goalProgress}%` }} /></div>
            <span>{goal.week_earnings >= goal.weekly_goal ? 'Goal reached!' : `${money(goal.weekly_goal - goal.week_earnings)} away`}</span>
          </> : <span className="dqx-muted">No weekly goal set yet.</span>}
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('earnings')}>View Insights</button>
        </div>
      </article>}

      <section className="dqx-stat-row">
        <StatCard icon={<Route />} tone="red" label="Today's Trips" value={String(today.trips)} note={numDelta(today.trips, yesterday.trips)} />
        <StatCard icon={<WalletCards />} tone="amber" label="Net Earnings" value={money(today.earnings)} note={pctDelta(today.earnings, yesterday.earnings)} />
        <StatCard icon={<MapPin />} tone="blue" label="Distance" value={`${today.distance_km.toFixed(1)} km`} note={numDelta(today.distance_km, yesterday.distance_km, ' km')} />
        <StatCard icon={<Clock />} tone="purple" label="Online Time" value={schedule?.current_session ? durationLabel(schedule.current_session.elapsed_minutes) : '0m'} note={is_online ? 'Current session' : 'Offline'} />
        <StatCard icon={<CircleCheckBig />} tone="green" label="Completion Rate" value={completionRate !== null ? `${completionRate}%` : '—'} note={completionDenominator > 0 ? `${today.completed}/${completionDenominator} today` : 'No trips yet'} />
      </section>

      <article className="dqx-map-panel">
        <div className="dqx-map-panel__overlay">
          {route ? <>
            <b className="dqx-map-panel__eyebrow">{active_trip ? 'ACTIVE ROUTE' : 'NEXT SCHEDULED TRIP'}</b>
            <div className="dqx-map-panel__route">{route.pickup_location} <span>→</span> {route.destination_location}</div>
            {next_scheduled && !active_trip && <p>{dateTimeOf(next_scheduled.scheduled_at)} · {next_scheduled.passenger_name}</p>}
            <button className="dqx-btn dqx-btn--primary dqx-btn--sm" onClick={() => onNavigate('trips')}>View in Trips</button>
          </> : <>
            <b className="dqx-map-panel__eyebrow">READY TO GO</b>
            <p className="dqx-muted">No active or scheduled trips right now.{is_online ? ' You\'re on shift and ready to open a departure.' : ''}</p>
          </>}
        </div>
        <div className="dqx-map-panel__canvas">
          <DriverLiveMap mapsConfig={boot.maps} />
        </div>
        <div className="dqx-map-panel__legend"><span className="is-you">Your live location</span></div>
      </article>

      <section className="dqx-dash-pair">
        <article className="dqx-card">
          <header><CalendarCheck /><h3>Today's Schedule</h3><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('schedule')}>View Schedule</button></header>
          <div className="dqx-dash-pair__cols">
            <div><small>Current Shift</small><b>{todayAvailability && !todayAvailability.is_off ? `${todayAvailability.start_time} – ${todayAvailability.end_time}` : 'Off today'}</b>{schedule?.current_session && <span className="dqx-chip is-active-chip">Active</span>}</div>
            <div><small>Next Scheduled Trip</small>{next_scheduled ? <><b>{timeOf(next_scheduled.scheduled_at)}</b><span>{next_scheduled.passenger_name}</span></> : <b className="dqx-muted">None</b>}</div>
          </div>
        </article>
        <article className="dqx-card">
          <header><Car /><h3>Vehicle Status</h3><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('vehicle')}>View Details</button></header>
          {vehicle?.vehicle ? <div className="dqx-vehicle-status">
            <div className="dqx-vehicle-status__id"><b>{vehicle.vehicle.make_model}</b><span>{vehicle.vehicle.plate_number}</span><em className={vehicle.vehicle.status === 'active' ? 'is-ok' : ''}>{vehicle.vehicle.status === 'active' ? 'Ready' : 'Inactive'}</em></div>
            <ul className="dqx-vehicle-status__checks">
              <li>{vehicleDocStatus === 'valid' || vehicleDocStatus === null ? <CircleCheckBig className="is-ok" /> : <AlertTriangle className="is-warn" />}<span>Documents</span><b>{vehicleDocStatus === null ? 'Not recorded' : vehicleDocStatus === 'valid' ? 'Valid' : vehicleDocStatus === 'expiring_soon' ? 'Expiring soon' : 'Expired'}</b></li>
              <li>{vehicle.open_issues.length === 0 ? <CircleCheckBig className="is-ok" /> : <AlertTriangle className="is-warn" />}<span>Open Issues</span><b>{vehicle.open_issues.length}</b></li>
              <li><Gauge /><span>Mileage</span><b>{vehicle.vehicle.current_mileage_km !== null ? `${vehicle.vehicle.current_mileage_km.toLocaleString()} km` : 'Not recorded'}</b></li>
            </ul>
          </div> : <p className="dqx-muted">No vehicle profile set up yet. <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('vehicle')}>Set up now</button></p>}
        </article>
      </section>

      <article className="dqx-assistant-bar">
        <div className="dqx-assistant-bar__icon"><CircleCheckBig /></div>
        <div className="dqx-assistant-bar__text"><b>AI ASSISTANT <em>BETA</em></b><p>{assistantMessage(dashboard)}</p></div>
        <div className="dqx-assistant-bar__actions">
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('earnings')}>View Insights</button>
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('reports')}>Reports</button>
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('vehicle')}>Vehicle</button>
        </div>
      </article>
    </div>

    <aside className="dqx-dashboard__side">
      <article className="dqx-card">
        <header><b>CURRENT TRIP</b><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onNavigate('trips')}>View All</button></header>
        {queue?.active_run ? <div className="dqx-side-trip"><span className="dqx-chip is-active-chip">{queue.active_run.status === 'TRAVELLING' ? 'On Trip' : 'Loading'}</span><b>{queue.active_run.loading_location}</b><span>{Math.max(0, queue.active_run.capacity - queue.active_run.remaining_seats)} of {queue.active_run.capacity} seats filled</span></div>
          : active_trip ? <div className="dqx-side-trip"><StatusChip status={active_trip.status} /><b>{active_trip.passenger_name}</b><span>{active_trip.pickup_location} → {active_trip.destination_location}</span></div> : <p className="dqx-muted">No active trip. Open a departure when you're ready to load passengers.</p>}
        <button className="dqx-btn dqx-btn--ghost" onClick={() => onNavigate('trips')}>View Trips</button>
      </article>

      <article className="dqx-card">
        <header><b>QUICK ACTIONS</b></header>
        <div className="dqx-quick-actions">
          <button className="dqx-quick-action is-red" disabled={busy} onClick={() => void scheduleAct({ action: is_online ? 'end_shift' : 'start_shift' })}>{is_online ? <WifiOff /> : <Wifi />}{is_online ? 'End Shift' : 'Start Shift'}</button>
          <button className="dqx-quick-action is-blue" onClick={() => onNavigate('earnings')}><WalletCards />My Earnings</button>
          <button className="dqx-quick-action is-dark" onClick={() => onNavigate('messages')}><MessageSquare />Messages{unreadMessages > 0 && <em>{unreadMessages}</em>}</button>
          <button className="dqx-quick-action is-purple" onClick={() => onNavigate('settings')}><ShieldAlert />Safety Center</button>
        </div>
      </article>

      <article className="dqx-card">
        <header><b>ALERTS</b></header>
        {alerts.length === 0 ? <div className="dqx-alert-row is-ok"><CircleCheckBig /><div><b>No warnings</b><span>All systems are normal.</span></div></div> :
          alerts.slice(0, 3).map((alertItem, index) => <div key={index} className={`dqx-alert-row is-${alertItem.tone}`}><AlertTriangle /><div><b>{alertItem.title}</b><span>{alertItem.detail}</span></div></div>)}
      </article>
    </aside>
  </div>;
}

// Compact "there's an active departure" summary for the Dashboard tab — the
// full add/accept/monitor cockpit lives on the Trips tab (see DepartureCockpit).
function ActiveDeparturePanel({ run, sessions, onManage }: { run: CoordinationRun; sessions: CoordinationQueueSession[]; onManage: () => void }) {
  const occupied = Math.max(0, run.capacity - run.remaining_seats);
  const departsInMinutes = Math.round((new Date(run.planned_departure_at).getTime() - Date.now()) / 60_000);
  const waiting = sessions.filter(session => session.status === 'PENDING_VENDOR').length;
  return <article className="dqx-active">
    <header><span className="dqx-active__badge">{run.status === 'TRAVELLING' ? 'ON TRIP' : 'PASSENGER LOADING'}</span><span>{run.loading_location}</span></header>
    <h2>{occupied} of {run.capacity} seats filled</h2>
    <div className="dqx-active__meta">
      <span>{run.status === 'TRAVELLING' ? 'En route to destination' : departsInMinutes > 0 ? `Departs in ${departsInMinutes}m` : 'Departure time has passed'}</span>
      {waiting > 0 && <span>{waiting} request{waiting === 1 ? '' : 's'} waiting</span>}
    </div>
    <div className="dqx-active__actions">
      <button className="dqx-btn dqx-btn--primary" onClick={onManage}>Manage Departure</button>
    </div>
  </article>;
}

function OpenDepartureModal({ boot, onClose, onOpened }: { boot: Boot; onClose: () => void; onOpened: () => void }) {
  const minimum = new Date(Date.now() + 5 * 60_000).toISOString().slice(0, 16);
  const [options, setOptions] = useState<DriverSessionOption[]>([]);
  const [selected, setSelected] = useState('');
  const [departureAt, setDepartureAt] = useState(minimum);
  const [remainingSeats, setRemainingSeats] = useState('');
  const [loadingLocation, setLoadingLocation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const current = options.find(option => option.profile_id === selected) || null;

  useEffect(() => {
    apiPost<{ result: { options: DriverSessionOption[] } }>('vendor/profiles.php', { action: 'transport_session_options' }, boot.csrf_token)
      .then(response => {
        const next = response.result.options || []; setOptions(next);
        if (next[0]) { setSelected(next[0].profile_id); setRemainingSeats(String(next[0].capacity)); setLoadingLocation(next[0].loading_location); }
      })
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Unable to load your published transport services.'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const choose = (profileId: string) => { const option = options.find(item => item.profile_id === profileId); setSelected(profileId); if (option) { setRemainingSeats(String(option.capacity)); setLoadingLocation(option.loading_location); } };

  const open = async () => {
    if (!current) return; setBusy(true); setError('');
    try {
      await apiPost('coordination/action.php', { action: 'create_run', service_id: current.service_id, seat_class_id: current.seat_class_id, remaining_seats: Number(remainingSeats), planned_departure_at: new Date(departureAt).toISOString(), loading_location: loadingLocation }, boot.csrf_token);
      const runsResponse = await apiGet<{ coordination: DepartureQueue }>('coordination/vendor-queue.php');
      const runId = runsResponse.coordination.active_run?.id;
      // A departure starts life as SCHEDULED; advance it straight to LOADING
      // so "Open Departure" reads as one action, matching the cockpit's
      // IDLE → PASSENGER LOADING model (no separate "not yet open" substate).
      if (runId) await apiPost('coordination/action.php', { action: 'update_run', run_id: runId, status: 'LOADING' }, boot.csrf_token);
      onOpened();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not open this departure.'); }
    finally { setBusy(false); }
  };

  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Open Departure</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      {options.length === 0 ? <p className="dqx-muted">No published transport service yet. Publish one in Driver Operations settings first.</p> : <>
        <label>Service<select value={selected} onChange={event => choose(event.target.value)}>{options.map(option => <option key={option.profile_id} value={option.profile_id}>{option.name} · {option.origin} → {option.destination}</option>)}</select></label>
        {current && <p className="dqx-muted">Capacity {current.capacity} · Fare {money(current.fare)} · Inventory available {current.inventory_remaining}</p>}
        <label>Departure date and time<input type="datetime-local" required min={minimum} value={departureAt} onChange={event => setDepartureAt(event.target.value)} /></label>
        <label>Seats physically free<input type="number" required min="1" max={current?.capacity || 500} value={remainingSeats} onChange={event => setRemainingSeats(event.target.value)} /></label>
        <label>Loading point<input required value={loadingLocation} onChange={event => setLoadingLocation(event.target.value)} /></label>
      </>}
    </div>
    {error && <p className="dqx-error">{error}</p>}
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy || !current} onClick={() => void open()}>{busy ? 'Opening…' : 'Open Departure'}</button></footer>
  </div></div>;
}

const PASSENGER_STATUS_LABELS: Record<string, string> = {
  PENDING_VENDOR: 'Requesting', ACCEPTED: 'Waiting', CUSTOMER_EN_ROUTE: 'On the way', ARRIVED_AT_PICKUP: 'Arrived',
  BOARDING_REQUESTED: 'Boarding requested', BOARDED: 'Boarded', NO_SHOW: 'No-show', CUSTOMER_CANCELLED: 'Cancelled',
  DECLINED: 'Declined', EXPIRED: 'Expired',
};
const RUN_ISSUE_CATEGORIES = ['vehicle', 'accident', 'passenger', 'route', 'medical', 'other'];

// The live operational cockpit for one departure: PASSENGER LOADING while
// tie_transport_runs.status is SCHEDULED/LOADING, TRAVELING once it flips to
// TRAVELLING, then a locally-captured completion summary (the run drops out
// of vendorQueue()'s active_run the instant it becomes COMPLETED server-side,
// so the summary must be captured client-side right before that call).
function DepartureCockpit({ boot, run, sessions, onRefresh }: { boot: Boot; run: CoordinationRun; sessions: CoordinationQueueSession[]; onRefresh: () => void }) {
  const [ledger, setLedger] = useState<PaymentLedger | null>(null);
  const [readiness, setReadiness] = useState<DepartureReadiness | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [contactSessionId, setContactSessionId] = useState('');
  const [callSessionId, setCallSessionId] = useState('');
  const [cashSession, setCashSession] = useState<CoordinationQueueSession | null>(null);
  const [issueOpen, setIssueOpen] = useState(false);
  const [walkInOpen, setWalkInOpen] = useState(false);
  const [completion, setCompletion] = useState<{ passengerCount: number; ledger: PaymentLedger; durationMinutes: number } | null>(null);

  const loadLedger = () => apiGet<{ result: { ledger: PaymentLedger; readiness: DepartureReadiness } }>('transport-payment/ledger.php', { run_id: run.id })
    .then(response => { setLedger(response.result.ledger); setReadiness(response.result.readiness); }).catch(() => undefined);

  useEffect(() => { void loadLedger(); const interval = window.setInterval(loadLedger, 5_000); return () => window.clearInterval(interval); }, [run.id, run.status]);

  // Broadcast the driver's live position to every real (non-walk-in)
  // passenger's session, so their app can show the driver moving in
  // real time — the same live location snapshot the driver's own map reads.
  const shareableSessionIds = sessions
    .filter(session => session.booking_source !== 'walk_in' && ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED', 'BOARDED'].includes(session.status))
    .map(session => session.id).sort().join(',');
  useEffect(() => {
    if (!shareableSessionIds || !navigator.geolocation) return;
    const sessionIds = shareableSessionIds.split(',');
    const broadcast = (position: GeolocationPosition) => {
      const location = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: position.coords.accuracy, permission: 'GRANTED' as const, source: 'browser_geolocation' as const };
      sessionIds.forEach(sessionId => { void apiPost('coordination/action.php', { action: 'location', session_id: sessionId, location }, boot.csrf_token).catch(() => undefined); });
    };
    const watchId = navigator.geolocation.watchPosition(broadcast, () => undefined, { enableHighAccuracy: true, maximumAge: 8_000, timeout: 20_000 });
    return () => navigator.geolocation.clearWatch(watchId);
  }, [shareableSessionIds, boot.csrf_token]);

  const runAction = async (payload: Record<string, unknown>, errorMessage: string) => {
    setBusy(true); setError('');
    try { await apiPost('coordination/action.php', payload, boot.csrf_token); onRefresh(); await loadLedger(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : errorMessage); }
    finally { setBusy(false); }
  };

  const decide = (sessionId: string, decision: 'ACCEPT' | 'DECLINE') => void runAction({ action: 'vendor_decision', session_id: sessionId, decision }, 'That request is no longer available.');
  const confirmBoarding = (sessionId: string) => void runAction({ action: 'confirm_boarding', session_id: sessionId }, 'Could not confirm boarding.');
  const markNoShow = (sessionId: string) => void runAction({ action: 'mark_no_show', session_id: sessionId }, 'Could not mark this passenger as a no-show.');
  const dropOff = (sessionId: string) => void runAction({ action: 'confirm_dropped_off', session_id: sessionId }, 'Could not confirm this passenger was dropped off.');
  const startTrip = () => void runAction({ action: 'update_run', run_id: run.id, status: 'TRAVELLING' }, 'Could not start the trip.');

  const boardedSessions = sessions.filter(session => session.status === 'BOARDED');

  const arrive = async () => {
    const summary = {
      passengerCount: boardedSessions.reduce((total, session) => total + session.passenger_count, 0),
      ledger: ledger || { paid_count: 0, outstanding_count: 0, outstanding_amount: 0, cash_amount: 0, digital_amount: 0, entries: [] },
      durationMinutes: run.travelling_started_at ? Math.max(0, Math.round((Date.now() - new Date(run.travelling_started_at).getTime()) / 60_000)) : 0,
    };
    setBusy(true); setError('');
    try { await apiPost('coordination/action.php', { action: 'update_run', run_id: run.id, status: 'COMPLETED' }, boot.csrf_token); setCompletion(summary); onRefresh(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not complete the trip.'); }
    finally { setBusy(false); }
  };

  if (completion) return <CompletedSummary summary={completion} onClose={() => setCompletion(null)} />;

  const mode: 'LOADING' | 'TRAVELING' = run.status === 'TRAVELLING' ? 'TRAVELING' : 'LOADING';

  return <div className="dqx-cockpit">
    {mode === 'LOADING'
      ? <LoadingBoard boot={boot} run={run} sessions={sessions} ledger={ledger} readiness={readiness} busy={busy} onDecide={decide} onConfirmBoarding={confirmBoarding} onMarkNoShow={markNoShow} onOpenCash={setCashSession} onOpenContact={setContactSessionId} onOpenCall={setCallSessionId} onStartTrip={startTrip} onAddWalkIn={() => setWalkInOpen(true)} />
      : <TravelingPanel boot={boot} run={run} boardedSessions={boardedSessions} ledger={ledger} busy={busy} onArrive={() => void arrive()} onReportIssue={() => setIssueOpen(true)} onOpenContact={setContactSessionId} onOpenCall={setCallSessionId} onDropOff={dropOff} />}
    {error && <p className="dqx-error">{error}</p>}
    {contactSessionId && <ContactDrawer boot={boot} sessionId={contactSessionId} onClose={() => setContactSessionId('')} />}
    {callSessionId && <CallDrawer boot={boot} sessionId={callSessionId} onClose={() => setCallSessionId('')} />}
    {cashSession && <CashPaymentModal boot={boot} session={cashSession} onClose={() => setCashSession(null)} onConfirmed={() => { setCashSession(null); void loadLedger(); }} />}
    {issueOpen && <ReportIssueModal boot={boot} runId={run.id} onClose={() => setIssueOpen(false)} />}
    {walkInOpen && <WalkInModal boot={boot} runId={run.id} onClose={() => setWalkInOpen(false)} onAdded={() => { setWalkInOpen(false); onRefresh(); void loadLedger(); }} />}
  </div>;
}

function LoadingBoard({ boot, run, sessions, ledger, readiness, busy, onDecide, onConfirmBoarding, onMarkNoShow, onOpenCash, onOpenContact, onOpenCall, onStartTrip, onAddWalkIn }: {
  boot: Boot; run: CoordinationRun; sessions: CoordinationQueueSession[]; ledger: PaymentLedger | null; readiness: DepartureReadiness | null; busy: boolean;
  onDecide: (sessionId: string, decision: 'ACCEPT' | 'DECLINE') => void; onConfirmBoarding: (sessionId: string) => void; onMarkNoShow: (sessionId: string) => void;
  onOpenCash: (session: CoordinationQueueSession) => void; onOpenContact: (sessionId: string) => void; onOpenCall: (sessionId: string) => void; onStartTrip: () => void; onAddWalkIn: () => void;
}) {
  const occupied = Math.max(0, run.capacity - run.remaining_seats);
  const departsInMinutes = Math.round((new Date(run.planned_departure_at).getTime() - Date.now()) / 60_000);
  const paidTotal = (ledger?.paid_count || 0) + (ledger?.outstanding_count || 0);

  return <>
    <header className="dqx-cockpit-banner is-loading">
      <span className="dqx-cockpit-banner__dot" /><b>PASSENGER LOADING</b>
      <span>{run.loading_location}</span>
      <span>{departsInMinutes > 0 ? `Departs in ${departsInMinutes}m` : 'Departure time has passed'}</span>
      <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={run.remaining_seats <= 0} onClick={onAddWalkIn}><Plus /> Add Walk-in</button>
    </header>

    <article className="dqx-assistant-bar">
      <div className="dqx-assistant-bar__icon"><CircleCheckBig /></div>
      <div className="dqx-assistant-bar__text"><b>OPERATIONS ASSISTANT</b><p>{loadingGuidance(sessions, readiness)}</p></div>
    </article>

    <article className="dqx-map-panel">
      <div className="dqx-map-panel__canvas"><DriverLiveMap mapsConfig={boot.maps} passengers={sessions.filter(session => session.location).map(session => ({ id: session.id, name: session.customer_name || 'Passenger', latitude: session.location!.latitude, longitude: session.location!.longitude }))} /></div>
      <div className="dqx-map-panel__legend"><span className="is-you">You</span><span className="is-passenger">Passengers</span></div>
    </article>

    <div className="dqx-cockpit-widgets">
      <article className="dqx-cockpit-widget">
        <b>SEATS</b>
        <div className="dqx-capacity-dots">{Array.from({ length: run.capacity }).map((_, index) => <span key={index} className={index < occupied ? 'is-full' : ''} />)}</div>
        <span>{occupied} occupied · {run.remaining_seats} available</span>
      </article>
      <article className="dqx-cockpit-widget">
        <b>PAYMENT STATUS</b>
        {ledger ? <>
          <div className="dqx-payment-bar"><i style={{ width: `${paidTotal > 0 ? (ledger.paid_count / paidTotal) * 100 : 0}%` }} /></div>
          <span>{ledger.paid_count} Paid · {ledger.outstanding_count} Pending{ledger.outstanding_amount > 0 ? ` · ${money(ledger.outstanding_amount)} outstanding` : ''}</span>
        </> : <span className="dqx-muted">Loading…</span>}
      </article>
      <article className={`dqx-cockpit-widget dqx-readiness ${readiness?.ready ? 'is-ready' : 'is-warn'}`}>
        <b>DEPARTURE READINESS</b>
        {readiness ? <span>{readiness.ready ? '✓ Ready for departure' : `${readiness.unresolved} unresolved · ${readiness.outstanding_count} unpaid`}</span> : <span className="dqx-muted">Checking…</span>}
        <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={onStartTrip}>Start Trip</button>
      </article>
    </div>

    <div className="dqx-passenger-board">
      {sessions.length === 0 && <div className="dqx-empty"><p>No passenger requests yet.</p></div>}
      {sessions.map(session => <PassengerCard key={session.id} session={session} busy={busy} onDecide={onDecide} onConfirmBoarding={onConfirmBoarding} onMarkNoShow={onMarkNoShow} onOpenCash={onOpenCash} onOpenContact={onOpenContact} onOpenCall={onOpenCall} ledgerEntry={ledger?.entries.find(entry => entry.session_id === session.id)} />)}
    </div>
  </>;
}

function PassengerCard({ session, busy, onDecide, onConfirmBoarding, onMarkNoShow, onOpenCash, onOpenContact, onOpenCall, ledgerEntry }: {
  session: CoordinationQueueSession; busy: boolean; onDecide: (sessionId: string, decision: 'ACCEPT' | 'DECLINE') => void; onConfirmBoarding: (sessionId: string) => void;
  onMarkNoShow: (sessionId: string) => void; onOpenCash: (session: CoordinationQueueSession) => void; onOpenContact: (sessionId: string) => void; onOpenCall: (sessionId: string) => void; ledgerEntry?: PaymentLedgerEntry;
}) {
  const paid = ledgerEntry?.payment_state === 'PAID';
  const isWalkIn = session.booking_source === 'walk_in';
  const canNoShow = ['ACCEPTED', 'CUSTOMER_EN_ROUTE', 'ARRIVED_AT_PICKUP', 'BOARDING_REQUESTED'].includes(session.status);
  return <article className={`dqx-passenger-card is-${session.status.toLowerCase().replaceAll('_', '-')}`}>
    <header>
      <span className={`dqx-passenger-card__dot ${session.status === 'BOARDED' ? 'is-on' : ''}`} />
      <b>{session.customer_name || 'Passenger'}</b>
      {session.status === 'BOARDED' ? <span className={`dqx-pay-badge ${paid ? 'is-paid' : 'is-unpaid'}`}>{paid ? 'PAID' : 'UNPAID'}</span> : <span className="dqx-status-badge">{PASSENGER_STATUS_LABELS[session.status] || session.status}</span>}
    </header>
    <p className="dqx-muted">{session.passenger_count} seat{session.passenger_count === 1 ? '' : 's'}{isWalkIn ? ' · Walk-in' : ''}{session.destination ? ` · To ${session.destination}` : ''}</p>
    {session.status === 'CUSTOMER_EN_ROUTE' && session.en_route_since && (() => {
      const minutes = Math.max(0, Math.round((Date.now() - new Date(session.en_route_since).getTime()) / 60_000));
      const isLong = minutes > LONG_WALK_MINUTES;
      return <p className={isLong ? 'dqx-walk-alert' : 'dqx-muted'}>{isLong && <AlertTriangle />} Walking for {minutes} min{isLong ? ' — consider checking in' : ''}</p>;
    })()}
    {paid && ledgerEntry?.method && <p className="dqx-muted">Paid via {ledgerEntry.method === 'cash' ? 'cash' : ledgerEntry.method === 'mobile_money' ? 'Mobile Money' : 'Bank'}{ledgerEntry.amount !== null ? ` · ${money(ledgerEntry.amount)}` : ''}</p>}
    {session.status === 'ARRIVED_AT_PICKUP' && <p className="dqx-muted">Waiting for the passenger to confirm boarding.</p>}
    <div className="dqx-passenger-card__row">
      {session.status === 'PENDING_VENDOR' && <>
        <button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy} onClick={() => onDecide(session.id, 'ACCEPT')}>Accept</button>
        <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => onDecide(session.id, 'DECLINE')}>Decline</button>
      </>}
      {session.status === 'BOARDING_REQUESTED' && <button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy} onClick={() => onConfirmBoarding(session.id)}>Confirm Boarding</button>}
      {session.status === 'BOARDED' && !paid && <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => onOpenCash(session)}><Banknote /> Record Cash</button>}
      {canNoShow && <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => onMarkNoShow(session.id)}>Mark No-show</button>}
      {/* Walk-ins have no Uthenga account, so there's no one to message or call. */}
      {session.status !== 'PENDING_VENDOR' && !isWalkIn && <>
        <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onOpenCall(session.id)}><Phone /> Call</button>
        <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onOpenContact(session.id)}><MessageSquare /> Message</button>
      </>}
    </div>
  </article>;
}

function TravelingPanel({ boot, run, boardedSessions, ledger, busy, onArrive, onReportIssue, onOpenContact, onOpenCall, onDropOff }: {
  boot: Boot; run: CoordinationRun; boardedSessions: CoordinationQueueSession[]; ledger: PaymentLedger | null; busy: boolean;
  onArrive: () => void; onReportIssue: () => void; onOpenContact: (sessionId: string) => void; onOpenCall: (sessionId: string) => void; onDropOff: (sessionId: string) => void;
}) {
  const elapsedMinutes = run.travelling_started_at ? Math.max(0, Math.round((Date.now() - new Date(run.travelling_started_at).getTime()) / 60_000)) : 0;
  const onboard = boardedSessions.reduce((total, session) => total + session.passenger_count, 0);
  // Real destinations passengers actually declared, grouped and counted —
  // never a fabricated arrival order, since no routing/sequencing engine
  // exists to honestly claim which stop comes "next".
  const stopGroups = new Map<string, number>();
  boardedSessions.forEach(session => { const key = session.destination?.trim() || 'Destination not specified'; stopGroups.set(key, (stopGroups.get(key) || 0) + session.passenger_count); });
  return <>
    <header className="dqx-cockpit-banner is-traveling"><span className="dqx-cockpit-banner__dot" /><b>LIVE TRIP</b><span>From {run.loading_location}</span></header>
    <article className="dqx-assistant-bar">
      <div className="dqx-assistant-bar__icon"><CircleCheckBig /></div>
      <div className="dqx-assistant-bar__text"><b>OPERATIONS ASSISTANT</b><p>{travelingGuidance(run, ledger)}</p></div>
    </article>
    <div className="dqx-cockpit-widgets">
      <article className="dqx-cockpit-widget"><b>TRIP DURATION</b><span>{elapsedMinutes}m</span></article>
      <article className="dqx-cockpit-widget"><b>PASSENGERS</b><span>{onboard} onboard</span></article>
      <article className="dqx-cockpit-widget"><b>PAYMENT</b><span>{ledger ? `${ledger.paid_count}/${ledger.paid_count + ledger.outstanding_count} paid` : '—'}</span></article>
      {stopGroups.size > 0 && <article className="dqx-cockpit-widget">
        <b>STOPS ON THIS TRIP</b>
        <ul className="dqx-stops-list">{Array.from(stopGroups.entries()).map(([destination, count]) => <li key={destination}><span>{destination}</span><em>{count}</em></li>)}</ul>
      </article>}
    </div>
    <article className="dqx-map-panel">
      <div className="dqx-map-panel__canvas"><DriverLiveMap mapsConfig={boot.maps} passengers={boardedSessions.filter(session => session.location).map(session => ({ id: session.id, name: session.customer_name || 'Passenger', latitude: session.location!.latitude, longitude: session.location!.longitude }))} /></div>
      <div className="dqx-map-panel__legend"><span className="is-you">You</span><span className="is-passenger">Passengers onboard</span></div>
    </article>
    <div className="dqx-passenger-board dqx-passenger-board--compact">
      {boardedSessions.map(session => <article key={session.id} className="dqx-passenger-card is-boarded">
        <header><CircleCheckBig className="is-ok" /><b>{session.customer_name || 'Passenger'}</b></header>
        <p className="dqx-muted">{session.passenger_count} seat{session.passenger_count === 1 ? '' : 's'}{session.destination ? ` · To ${session.destination}` : ''}</p>
        <div className="dqx-passenger-card__row">
          <button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy} onClick={() => onDropOff(session.id)}>Confirm Dropped Off</button>
          {session.booking_source !== 'walk_in' && <>
            <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onOpenCall(session.id)}><Phone /> Call</button>
            <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => onOpenContact(session.id)}><MessageSquare /> Message</button>
          </>}
        </div>
      </article>)}
    </div>
    <div className="dqx-cockpit-actions">
      <button className="dqx-btn dqx-btn--ghost" onClick={onReportIssue}><AlertTriangle /> Report Issue</button>
      <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={onArrive}>Arrived at Destination</button>
    </div>
  </>;
}

function CompletedSummary({ summary, onClose }: { summary: { passengerCount: number; ledger: PaymentLedger; durationMinutes: number }; onClose: () => void }) {
  const revenue = summary.ledger.cash_amount + summary.ledger.digital_amount;
  return <div className="dqx-cockpit-completed">
    <CircleCheckBig className="dqx-cockpit-completed__icon" />
    <h2>Trip Completed</h2>
    <dl>
      <div><dt>Passengers</dt><dd>{summary.passengerCount}</dd></div>
      <div><dt>Revenue</dt><dd>{money(revenue)}</dd></div>
      <div><dt>Cash</dt><dd>{money(summary.ledger.cash_amount)}</dd></div>
      <div><dt>Digital</dt><dd>{money(summary.ledger.digital_amount)}</dd></div>
      <div><dt>Duration</dt><dd>{summary.durationMinutes}m</dd></div>
    </dl>
    <button className="dqx-btn dqx-btn--primary" onClick={onClose}>Close Trip</button>
  </div>;
}

function CashPaymentModal({ boot, session, onClose, onConfirmed }: { boot: Boot; session: CoordinationQueueSession; onClose: () => void; onConfirmed: () => void }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const confirm = async () => {
    setBusy(true); setError('');
    try { await apiPost('transport-payment/cash.php', { session_id: session.id }, boot.csrf_token); onConfirmed(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not record this cash payment.'); }
    finally { setBusy(false); }
  };
  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Record Cash Payment</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <p>{session.customer_name || 'Passenger'} · {session.passenger_count} seat{session.passenger_count === 1 ? '' : 's'}</p>
      <p className="dqx-muted">Confirming records this trip as paid by cash, attributed to you. An electronically-verified payment can never be overwritten this way.</p>
    </div>
    {error && <p className="dqx-error">{error}</p>}
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void confirm()}>{busy ? 'Confirming…' : 'Confirm Cash Payment'}</button></footer>
  </div></div>;
}

function ReportIssueModal({ boot, runId, onClose }: { boot: Boot; runId: string; onClose: () => void }) {
  const [category, setCategory] = useState('vehicle');
  const [description, setDescription] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);
  const submit = async () => {
    if (!description.trim()) return; setBusy(true); setError('');
    try { await apiPost('coordination/action.php', { action: 'report_issue', run_id: runId, category, description }, boot.csrf_token); setDone(true); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not report this issue.'); }
    finally { setBusy(false); }
  };
  if (done) return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Issue Reported</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body"><p>Your report has been recorded.</p></div>
    <footer><button className="dqx-btn dqx-btn--primary" onClick={onClose}>Done</button></footer>
  </div></div>;
  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Report Issue</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <label>Category<select value={category} onChange={event => setCategory(event.target.value)}>{RUN_ISSUE_CATEGORIES.map(item => <option key={item} value={item}>{titleCase(item)}</option>)}</select></label>
      <label>Description<textarea rows={3} required value={description} onChange={event => setDescription(event.target.value)} placeholder="Describe what happened…" /></label>
    </div>
    {error && <p className="dqx-error">{error}</p>}
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void submit()}>{busy ? 'Reporting…' : 'Report Issue'}</button></footer>
  </div></div>;
}

function WalkInModal({ boot, runId, onClose, onAdded }: { boot: Boot; runId: string; onClose: () => void; onAdded: () => void }) {
  const [name, setName] = useState('');
  const [seats, setSeats] = useState('1');
  const [destination, setDestination] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const submit = async () => {
    if (!name.trim()) return; setBusy(true); setError('');
    try { await apiPost('coordination/action.php', { action: 'add_walk_in', run_id: runId, walk_in_name: name.trim(), passenger_count: Number(seats) || 1, destination: destination.trim() || undefined }, boot.csrf_token); onAdded(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not add this walk-in passenger.'); }
    finally { setBusy(false); }
  };
  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Add Walk-in Passenger</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <label>Passenger name<input required value={name} onChange={event => setName(event.target.value)} placeholder="Full name" /></label>
      <label>Seats<input type="number" min="1" max="20" value={seats} onChange={event => setSeats(event.target.value)} /></label>
      <label>Destination<input value={destination} onChange={event => setDestination(event.target.value)} placeholder="Optional — e.g. Area 25" /></label>
      <p className="dqx-muted">This passenger has no Uthenga account. They'll be charged this departure's usual fare, and go through the same Confirm Boarding step as everyone else.</p>
    </div>
    {error && <p className="dqx-error">{error}</p>}
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy || !name.trim()} onClick={() => void submit()}>{busy ? 'Adding…' : 'Add Passenger'}</button></footer>
  </div></div>;
}

function ContactDrawer({ boot, sessionId, onClose }: { boot: Boot; sessionId: string; onClose: () => void }) {
  return <div className="dqx-drawer-backdrop" onMouseDown={onClose}><aside className="dqx-drawer" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Message Passenger</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-drawer__body"><CoordinationChatPanel boot={boot} sessionId={sessionId} hasActiveTrip={true} /></div>
  </aside></div>;
}

// A one-tap "Call" entry point: opens straight into the WebRTC call panel and
// starts ringing immediately, instead of requiring the driver to open the
// chat thread first and press Call from inside it (see CallPanel autoStart).
function CallDrawer({ boot, sessionId, onClose }: { boot: Boot; sessionId: string; onClose: () => void }) {
  const [detail, setDetail] = useState<CoordinationSessionDetail | null>(null);
  const [error, setError] = useState('');

  const load = () => apiGet<{ coordination: CoordinationSessionDetail }>('coordination/session.php', { session_id: sessionId })
    .then(response => { setDetail(response.coordination); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'This live session is unavailable.'));

  useEffect(() => { void load(); const interval = window.setInterval(load, 3_000); return () => window.clearInterval(interval); }, [sessionId]);

  return <div className="dqx-drawer-backdrop" onMouseDown={onClose}><aside className="dqx-drawer dqx-drawer--call" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Call {detail?.session.customer_name || 'Passenger'}</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-drawer__body">
      {error && <p className="dqx-error">{error}</p>}
      {detail && <CallPanel boot={boot} sessionId={sessionId} call={detail.call} allowedActions={detail.workspace.allowed_actions} viewerRole={detail.viewer_role} peerLabel="Passenger" autoStart onRefresh={() => void load()} />}
      {!detail && !error && <p className="dqx-muted">Connecting…</p>}
    </div>
  </aside></div>;
}

function Kpi({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return <article className={`dqx-kpi ${accent ? `is-${accent}` : ''}`}><small>{label}</small><b>{value}</b></article>;
}

function ActiveTripPanel({ trip, busy, act, onOpenComplete }: { trip: Trip; busy: boolean; act: (payload: Record<string, unknown>) => Promise<any>; onOpenComplete: (trip: Trip) => void }) {
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState('');
  const next = ACTIVE_NEXT[trip.status];

  return <article className="dqx-active">
    <header><span className="dqx-active__badge">ACTIVE TRIP</span><StatusChip status={trip.status} /></header>
    <h2>{trip.passenger_name}</h2>
    {trip.passenger_phone && <p className="dqx-active__phone">{trip.passenger_phone}</p>}
    <div className="dqx-active__route"><MapPin /><span>{trip.pickup_location}</span><span className="dqx-active__arrow">↓</span><MapPin /><span>{trip.destination_location}</span></div>
    <div className="dqx-active__meta"><span>{money(trip.estimated_fare)}</span>{trip.vehicle_label && <span>{trip.vehicle_label}{trip.vehicle_plate ? ` · ${trip.vehicle_plate}` : ''}</span>}</div>
    <div className="dqx-active__actions">
      {next && <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void act({ action: 'advance', trip_id: trip.id, target_status: next.target })}>{next.label}</button>}
      {trip.status === 'IN_PROGRESS' && <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => onOpenComplete(trip)}>Complete Trip</button>}
      {trip.status === 'ARRIVED' && <button className="dqx-btn dqx-btn--ghost" disabled={busy} onClick={() => void act({ action: 'no_show', trip_id: trip.id })}>Mark No-show</button>}
      {trip.passenger_phone ? <PhoneContactActions phone={trip.passenger_phone} /> : <button type="button" className="dqx-btn dqx-btn--ghost is-disabled" disabled><Phone /> No number on file</button>}
      {CANCELLABLE_STATUSES.includes(trip.status) && (!cancelling
        ? <button className="dqx-btn dqx-btn--danger-ghost" onClick={() => setCancelling(true)}>Cancel Trip</button>
        : <div className="dqx-cancel-inline"><input value={reason} onChange={event => setReason(event.target.value)} placeholder="Reason (optional)" /><button className="dqx-btn dqx-btn--danger-ghost" disabled={busy} onClick={() => { void act({ action: 'cancel', trip_id: trip.id, reason }); setCancelling(false); }}>Confirm cancel</button><button className="dqx-btn dqx-btn--ghost" onClick={() => setCancelling(false)}>Back</button></div>)}
    </div>
  </article>;
}

function CompletionFlow({ trip, result, busy, onConfirm, onClose }: { trip: Trip; result: Trip | null; busy: boolean; onConfirm: (input: Record<string, unknown>) => void; onClose: () => void }) {
  const [fare, setFare] = useState(trip.estimated_fare !== null ? String(trip.estimated_fare) : '');
  const [method, setMethod] = useState<'digital' | 'cash'>('digital');
  const [distance, setDistance] = useState('');
  const [duration, setDuration] = useState('');

  if (result) return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal dqx-modal--success" onMouseDown={event => event.stopPropagation()}>
    <ReceiptText className="dqx-modal__success-icon" />
    <h3>Trip Completed</h3>
    <b>{money(result.final_fare)}</b>
    <span>Payment: {result.payment_status === 'paid' ? 'Paid' : result.payment_status}</span>
    <div className="dqx-modal__actions">
      {result.passenger_phone && <PhoneContactActions phone={result.passenger_phone} />}
      <button className="dqx-btn dqx-btn--primary" onClick={onClose}>Done</button>
    </div>
  </div></div>;

  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><div className="dqx-modal" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Complete Trip</h3><button onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <label>Trip fare (MK)<input type="number" min="0" value={fare} onChange={event => setFare(event.target.value)} /></label>
      <label>Payment method<div className="dqx-segmented"><button type="button" className={method === 'digital' ? 'is-active' : ''} onClick={() => setMethod('digital')}>Digital</button><button type="button" className={method === 'cash' ? 'is-active' : ''} onClick={() => setMethod('cash')}>Cash</button></div></label>
      <label>Distance (km)<input type="number" min="0" step="0.1" value={distance} onChange={event => setDistance(event.target.value)} placeholder="Optional" /></label>
      <label>Duration (minutes)<input type="number" min="0" value={duration} onChange={event => setDuration(event.target.value)} placeholder="Optional" /></label>
    </div>
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy || fare === ''} onClick={() => onConfirm({ final_fare: Number(fare), payment_method: method, distance_km: distance === '' ? undefined : Number(distance), duration_seconds: duration === '' ? undefined : Number(duration) * 60 })}>{busy ? 'Confirming…' : 'Confirm Completion'}</button></footer>
  </div></div>;
}

const TRIP_TABS: { id: string; label: string }[] = [
  { id: 'all', label: 'All' }, { id: 'requested', label: 'Requested' }, { id: 'upcoming', label: 'Upcoming' },
  { id: 'active', label: 'Active' }, { id: 'completed', label: 'Completed' }, { id: 'cancelled', label: 'Cancelled' },
];

function exportCsv(trips: Trip[]) {
  const headers = ['Trip', 'Passenger', 'Phone', 'Pickup', 'Destination', 'Status', 'Scheduled', 'Fare', 'Payment'];
  const rows = trips.map(trip => [trip.trip_code, trip.passenger_name, trip.passenger_phone || '', trip.pickup_location, trip.destination_location, STATUS_META[trip.status]?.label || trip.status, trip.scheduled_at ? dateTimeOf(trip.scheduled_at) : '', String(trip.final_fare ?? trip.estimated_fare ?? ''), trip.payment_status]);
  const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a'); link.href = url; link.download = `uthenga-trips-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link); link.click(); document.body.removeChild(link); URL.revokeObjectURL(url);
}

// A Quick Taxi departure carries several passengers who share one vehicle
// from a loading point (a rank), not one passenger booked on-demand like a
// private hire. Configuring a departure — its loading point, departure time,
// and seat count — and then adding/accepting/monitoring passengers as they
// arrive is the primary job of this tab; see OpenDepartureModal/DepartureCockpit.
function TripsWorkspace({ boot, today, onSelectTrip, refreshKey }: { boot: Boot; today: Dashboard['today'] | null; onSelectTrip: (tripId: string) => void; refreshKey: number }) {
  const [list, setList] = useState<TripList | null>(null);
  const [filterTab, setFilterTab] = useState('all');
  const [q, setQ] = useState('');
  const [paymentStatus, setPaymentStatus] = useState('');
  const [date, setDate] = useState('');
  const [sort, setSort] = useState('newest');
  const [error, setError] = useState('');
  const [queue, setQueue] = useState<DepartureQueue | null>(null);
  const [openDepartureModal, setOpenDepartureModal] = useState(false);

  const load = () => apiGet<{ list: TripList }>('trip/list.php', { status: filterTab, q, payment_status: paymentStatus, date, sort })
    .then(response => { setList(response.list); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load trips.'));

  useEffect(() => { const timeout = window.setTimeout(load, q ? 300 : 0); return () => window.clearTimeout(timeout); }, [filterTab, q, paymentStatus, date, sort, refreshKey]);

  const loadQueue = () => apiGet<{ coordination: DepartureQueue }>('coordination/vendor-queue.php').then(response => setQueue(response.coordination)).catch(() => undefined);
  useEffect(() => { void loadQueue(); const interval = window.setInterval(loadQueue, 5_000); return () => window.clearInterval(interval); }, [refreshKey]);

  const counts = list?.counts || {};

  return <div className="dqx-trips">
    <header className="dqx-heading">
      <div><p className="dqx-eyebrow">QUICK TAXI · TRIPS</p><h1>Trips</h1><span>Configure a departure — loading point, departure time and seats — then add, accept and monitor passengers as they board.</span></div>
      <div className="dqx-heading__actions">
        <button className="dqx-btn dqx-btn--ghost" onClick={() => exportCsv(list?.trips || [])}><Download /> Export</button>
        {!queue?.active_run && <button className="dqx-btn dqx-btn--primary" onClick={() => setOpenDepartureModal(true)}><Plus /> Open Departure</button>}
      </div>
    </header>

    {queue?.active_run && <DepartureCockpit boot={boot} run={queue.active_run} sessions={queue.sessions} onRefresh={loadQueue} />}

    {!queue?.active_run && <>
      <section className="dqx-kpis">
        <Kpi label="Today's Trips" value={String(today?.trips ?? 0)} />
        <Kpi label="Active Trip" value={String(counts.active ?? 0)} accent="red" />
        <Kpi label="Upcoming" value={String(counts.upcoming ?? 0)} />
        <Kpi label="Completed" value={String(today?.completed ?? 0)} accent="green" />
        <Kpi label="Cancelled" value={String(today?.cancelled ?? 0)} accent="amber" />
        <Kpi label="Today's Earnings" value={money(today?.earnings ?? 0)} accent="green" />
      </section>

      <nav className="dqx-tabstrip">{TRIP_TABS.map(item => <button key={item.id} className={filterTab === item.id ? 'is-active' : ''} onClick={() => setFilterTab(item.id)}>{item.label}<em>{counts[item.id] ?? 0}</em></button>)}</nav>

      <div className="dqx-toolbar">
        <div className="dqx-search"><Search /><input value={q} onChange={event => setQ(event.target.value)} placeholder="Search by trip ID, passenger, phone, pickup or destination…" /></div>
        <input type="date" value={date} onChange={event => setDate(event.target.value)} />
        <select value={paymentStatus} onChange={event => setPaymentStatus(event.target.value)}><option value="">Any payment status</option><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option></select>
        <select value={sort} onChange={event => setSort(event.target.value)}><option value="newest">Newest</option><option value="pickup">Pickup time</option><option value="fare">Fare</option><option value="distance">Distance</option><option value="status">Status</option></select>
      </div>

      {error && <p className="dqx-error">{error}</p>}

      <div className="dqx-trip-list">
        {list && list.trips.length === 0 && <div className="dqx-empty"><p>No trips match these filters.</p></div>}
        {list?.trips.map(trip => <TripRow key={trip.id} trip={trip} onSelect={() => onSelectTrip(trip.id)} />)}
      </div>
    </>}

    <h4 className="dqx-section-title">Quick Taxi Departures</h4>
    <DeparturesSection />

    {openDepartureModal && <OpenDepartureModal boot={boot} onClose={() => setOpenDepartureModal(false)} onOpened={() => { setOpenDepartureModal(false); void loadQueue(); }} />}
  </div>;
}

type DepartureHistoryItem = CoordinationRun & { boarded_passengers: number; total_revenue: number; cash_revenue: number; digital_revenue: number };

function DeparturesSection() {
  const [departures, setDepartures] = useState<DepartureHistoryItem[]>([]);
  const [error, setError] = useState('');
  const [manifestRunId, setManifestRunId] = useState('');

  useEffect(() => {
    apiGet<{ coordination: { departures: DepartureHistoryItem[] } }>('coordination/departures.php')
      .then(response => setDepartures(response.coordination.departures))
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load Quick Taxi departures.'));
  }, []);

  return <>
    {error && <p className="dqx-error">{error}</p>}
    <div className="dqx-trip-list">
      {departures.length === 0 && !error && <div className="dqx-empty"><p>No completed Quick Taxi departures yet.</p></div>}
      {departures.map(departure => <article key={departure.id} className="dqx-trip-row" onClick={() => setManifestRunId(departure.id)}>
        <div className="dqx-trip-row__field"><small>Departure</small><b>{dateTimeOf(departure.planned_departure_at)}</b><span className="dqx-status-badge">{departure.status}</span></div>
        <div className="dqx-trip-row__field dqx-trip-row__route"><small>Loading Point</small><b><MapPin />{departure.loading_location}</b></div>
        <div className="dqx-trip-row__field"><small>Passengers</small><b>{departure.boarded_passengers}</b></div>
        <div className="dqx-trip-row__field"><small>Revenue</small><b>{money(departure.total_revenue)}</b></div>
        <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={event => { event.stopPropagation(); setManifestRunId(departure.id); }}>View Manifest</button>
      </article>)}
    </div>
    {manifestRunId && <DepartureManifestDrawer runId={manifestRunId} onClose={() => setManifestRunId('')} />}
  </>;
}

function DepartureManifestDrawer({ runId, onClose }: { runId: string; onClose: () => void }) {
  const [manifest, setManifest] = useState<{ run: CoordinationRun; sessions: CoordinationQueueSession[] } | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    apiGet<{ coordination: { run: CoordinationRun; sessions: CoordinationQueueSession[] } }>('coordination/departure.php', { run_id: runId })
      .then(response => setManifest(response.coordination))
      .catch(reason => setError(reason instanceof Error ? reason.message : 'This departure manifest is unavailable.'));
  }, [runId]);

  return <div className="dqx-drawer-backdrop" onMouseDown={onClose}><aside className="dqx-drawer" onMouseDown={event => event.stopPropagation()}>
    <header><h3>Departure Manifest</h3><button onClick={onClose}><X /></button></header>
    {error && <p className="dqx-error">{error}</p>}
    {!manifest && !error && <p className="dqx-muted">Loading manifest…</p>}
    {manifest && <div className="dqx-drawer__body">
      <dl className="dqx-detail-list">
        <div><dt>Loading Point</dt><dd>{manifest.run.loading_location}</dd></div>
        <div><dt>Status</dt><dd>{manifest.run.status}</dd></div>
        <div><dt>Departed</dt><dd>{manifest.run.actual_departure_at ? dateTimeOf(manifest.run.actual_departure_at) : '—'}</dd></div>
        <div><dt>Completed</dt><dd>{manifest.run.completed_at ? dateTimeOf(manifest.run.completed_at) : '—'}</dd></div>
      </dl>
      <h4>Passengers</h4>
      <ul className="dqx-history-list">
        {manifest.sessions.length === 0 && <li className="dqx-muted is-static">No passenger records for this departure.</li>}
        {manifest.sessions.map(session => <li key={session.id} className="is-static">
          <div><b>{session.customer_name || 'Passenger'}</b><span>{PASSENGER_STATUS_LABELS[session.status] || session.status}</span></div>
          <div className="dqx-history-route">{session.passenger_count} seat{session.passenger_count === 1 ? '' : 's'}{session.booking_source === 'walk_in' ? ' · Walk-in' : ''}</div>
        </li>)}
      </ul>
    </div>}
  </aside></div>;
}

function TripRow({ trip, onSelect }: { trip: Trip; onSelect: () => void }) {
  return <article className="dqx-trip-row" onClick={onSelect}>
    <div className="dqx-trip-row__field"><small>Trip</small><b>{trip.trip_code}</b></div>
    <div className="dqx-trip-row__field"><small>Passenger</small><b>{trip.passenger_name}</b></div>
    <div className="dqx-trip-row__field dqx-trip-row__route"><small>Route</small><b><MapPin />{trip.pickup_location}<span>→</span>{trip.destination_location}</b></div>
    <div className="dqx-trip-row__field"><small>Pickup</small><b>{trip.is_scheduled ? timeOf(trip.scheduled_at) : 'Now'}</b></div>
    <div className="dqx-trip-row__field"><small>Status</small><StatusChip status={trip.status} /></div>
    <div className="dqx-trip-row__field"><small>Fare</small><b>{money(trip.final_fare ?? trip.estimated_fare)}</b></div>
    <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={event => { event.stopPropagation(); onSelect(); }}>View</button>
  </article>;
}

function TripDrawer({ tripId, busy, act, onOpenComplete, onClose }: { tripId: string; busy: boolean; act: (payload: Record<string, unknown>) => Promise<any>; onOpenComplete: (trip: Trip) => void; onClose: () => void }) {
  const [detail, setDetail] = useState<TripDetail | null>(null);
  const [error, setError] = useState('');
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState('');

  const reload = () => apiGet<{ detail: TripDetail }>('trip/detail.php', { id: tripId }).then(response => { setDetail(response.detail); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Trip not found.'));
  useEffect(() => { void reload(); }, [tripId]);

  const runAction = async (payload: Record<string, unknown>) => { await act(payload); await reload(); };
  const nextAction = detail ? ACTIVE_NEXT[detail.trip.status] : undefined;

  return <div className="dqx-drawer-backdrop" onMouseDown={onClose}><aside className="dqx-drawer" onMouseDown={event => event.stopPropagation()}>
    <header><h3>{detail ? `Trip ${detail.trip.trip_code}` : 'Trip'}</h3><button onClick={onClose}><X /></button></header>
    {error && <p className="dqx-error">{error}</p>}
    {!detail && !error && <p className="dqx-muted">Loading trip…</p>}
    {detail && <div className="dqx-drawer__body">
      <StatusChip status={detail.trip.status} />
      <dl className="dqx-detail-list">
        <div><dt>Passenger</dt><dd>{detail.trip.passenger_name}{detail.trip.passenger_phone ? ` · ${detail.trip.passenger_phone}` : ''}</dd></div>
        <div><dt>Pickup</dt><dd>{detail.trip.pickup_location}</dd></div>
        <div><dt>Destination</dt><dd>{detail.trip.destination_location}</dd></div>
        <div><dt>{detail.trip.is_scheduled ? 'Scheduled' : 'Requested'}</dt><dd>{dateTimeOf(detail.trip.is_scheduled ? detail.trip.scheduled_at : detail.trip.requested_at)}</dd></div>
        {detail.trip.vehicle_label && <div><dt>Vehicle</dt><dd>{detail.trip.vehicle_label}{detail.trip.vehicle_plate ? ` · ${detail.trip.vehicle_plate}` : ''}</dd></div>}
        <div><dt>Fare</dt><dd>{money(detail.trip.final_fare ?? detail.trip.estimated_fare)}</dd></div>
        <div><dt>Payment</dt><dd>{detail.trip.payment_method ? `${detail.trip.payment_method === 'digital' ? 'Digital' : 'Cash'} · ` : ''}{detail.trip.payment_status}</dd></div>
        {detail.trip.cancellation_reason && <div><dt>Reason</dt><dd>{detail.trip.cancellation_reason}</dd></div>}
      </dl>

      <h4>Trip Timeline</h4>
      <TripChecklist trip={detail.trip} />

      {detail.timeline.length > 0 && <details className="dqx-activity"><summary>Full activity log</summary><ul>{detail.timeline.map((event, index) => <li key={index}><b>{event.new_status ? STATUS_META[event.new_status]?.label || event.new_status : event.event_type}</b><span>{dateTimeOf(event.created_at)}</span>{event.reason && <em>{event.reason}</em>}</li>)}</ul></details>}

      <div className="dqx-drawer__actions">
        {['REQUESTED', 'ASSIGNED'].includes(detail.trip.status) && <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void runAction({ action: 'accept', trip_id: detail.trip.id })}>Accept Trip</button>}
        {nextAction && <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void runAction({ action: 'advance', trip_id: detail.trip.id, target_status: nextAction.target })}>{nextAction.label}</button>}
        {detail.trip.status === 'IN_PROGRESS' && <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => onOpenComplete(detail.trip)}>Complete Trip</button>}
        {detail.trip.status === 'ARRIVED' && <button className="dqx-btn dqx-btn--ghost" disabled={busy} onClick={() => void runAction({ action: 'no_show', trip_id: detail.trip.id })}>Mark No-show</button>}
        {detail.trip.passenger_phone ? <PhoneContactActions phone={detail.trip.passenger_phone} /> : <button type="button" className="dqx-btn dqx-btn--ghost is-disabled" disabled><Phone /> No number on file</button>}
        {CANCELLABLE_STATUSES.includes(detail.trip.status) && (!cancelling
          ? <button className="dqx-btn dqx-btn--danger-ghost" onClick={() => setCancelling(true)}>Cancel Trip</button>
          : <div className="dqx-cancel-inline"><input value={reason} onChange={event => setReason(event.target.value)} placeholder="Reason (optional)" /><button className="dqx-btn dqx-btn--danger-ghost" disabled={busy} onClick={() => { void runAction({ action: 'cancel', trip_id: detail.trip.id, reason }); setCancelling(false); }}>Confirm cancel</button><button className="dqx-btn dqx-btn--ghost" onClick={() => setCancelling(false)}>Back</button></div>)}
      </div>
    </div>}
  </aside></div>;
}

const CHECKLIST_STAGES: { key: keyof Trip; label: string }[] = [
  { key: 'requested_at', label: 'Trip requested' },
  { key: 'accepted_at', label: 'Driver accepted' },
  { key: 'en_route_at', label: 'Driver en route' },
  { key: 'arrived_at', label: 'Driver arrived' },
  { key: 'onboard_at', label: 'Passenger onboard' },
  { key: 'completed_at', label: 'Trip completed' },
];
function TripChecklist({ trip }: { trip: Trip }) {
  if (['CANCELLED', 'NO_SHOW'].includes(trip.status)) return <p className="dqx-muted">This trip ended without completing — see the reason above.</p>;
  return <ul className="dqx-checklist">{CHECKLIST_STAGES.map(stage => { const value = trip[stage.key] as string | null; const done = Boolean(value); return <li key={stage.key} className={done ? 'is-done' : ''}>{done ? <CircleCheckBig /> : <Circle />}<span>{stage.label}</span>{done && <time>{timeOf(value)}</time>}</li>; })}</ul>;
}

const PASSENGER_TABS: { id: string; label: string }[] = [
  { id: 'all', label: 'All' }, { id: 'active', label: 'Active' }, { id: 'frequent', label: 'Frequent' }, { id: 'previous', label: 'Previous' },
];

function PassengersWorkspace({ boot, onSelectTrip, refreshKey }: { boot: Boot; onSelectTrip: (tripId: string) => void; refreshKey: number }) {
  const [summary, setSummary] = useState<PassengerSummary | null>(null);
  const [list, setList] = useState<PassengerList | null>(null);
  const [tab, setTab] = useState('all');
  const [q, setQ] = useState('');
  const [selectedKey, setSelectedKey] = useState('');
  const [error, setError] = useState('');

  const loadSummary = () => apiGet<{ summary: PassengerSummary }>('passenger/summary.php').then(response => setSummary(response.summary)).catch(() => undefined);
  const loadList = () => apiGet<{ list: PassengerList }>('passenger/list.php', { tab, q }).then(response => { setList(response.list); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load passengers.'));

  useEffect(() => { void loadSummary(); }, [refreshKey]);
  useEffect(() => { const timeout = window.setTimeout(loadList, q ? 300 : 0); return () => window.clearTimeout(timeout); }, [tab, q, refreshKey]);

  return <div className="dqx-passengers">
    <header className="dqx-heading"><div><p className="dqx-eyebrow">QUICK TAXI · PASSENGERS</p><h1>Passengers</h1><span>View passengers you've served and manage active passenger interactions.</span></div></header>

    {summary && <section className="dqx-kpis">
      <Kpi label="Today's Passengers" value={String(summary.today_passengers)} />
      <Kpi label="Repeat Passengers" value={String(summary.repeat_passengers)} />
      <Kpi label="Active Passenger" value={String(summary.active_passenger)} accent="red" />
      <Kpi label="Total Served" value={String(summary.total_served)} accent="green" />
    </section>}

    <nav className="dqx-tabstrip">{PASSENGER_TABS.map(item => <button key={item.id} className={tab === item.id ? 'is-active' : ''} onClick={() => setTab(item.id)}>{item.label}<em>{list?.counts[item.id] ?? 0}</em></button>)}</nav>

    <div className="dqx-toolbar"><div className="dqx-search"><Search /><input value={q} onChange={event => setQ(event.target.value)} placeholder="Search by passenger name, phone or trip ID…" /></div></div>

    {error && <p className="dqx-error">{error}</p>}

    <div className="dqx-trip-list">
      {list && list.passengers.length === 0 && <div className="dqx-empty"><p>No passengers match these filters.</p></div>}
      {list?.passengers.map(passenger => <PassengerRow key={passenger.passenger_key} passenger={passenger} onSelect={() => setSelectedKey(passenger.passenger_key)} />)}
    </div>

    {selectedKey && <PassengerDrawer boot={boot} passengerKey={selectedKey} csrf={boot.csrf_token} onSelectTrip={onSelectTrip} onClose={() => { setSelectedKey(''); void loadList(); void loadSummary(); }} />}
  </div>;
}

function PassengerRow({ passenger, onSelect }: { passenger: Passenger; onSelect: () => void }) {
  return <article className="dqx-trip-row" onClick={onSelect}>
    <div className="dqx-trip-row__field"><small>Passenger</small><b>{passenger.passenger_name}</b>{passenger.source === 'coordination' && <span className="dqx-status-badge">Quick Taxi</span>}</div>
    <div className="dqx-trip-row__field"><small>Phone</small><b>{passenger.passenger_phone || '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Trips</small><b>{passenger.trip_count}</b></div>
    <div className="dqx-trip-row__field"><small>Last Trip</small><b>{passenger.last_trip_at ? dateTimeOf(passenger.last_trip_at) : '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Status</small><PassengerStatusChip status={passenger.status} /></div>
    <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={event => { event.stopPropagation(); onSelect(); }}>View</button>
  </article>;
}

function PassengerDrawer({ boot, passengerKey, csrf, onSelectTrip, onClose }: { boot: Boot; passengerKey: string; csrf?: string; onSelectTrip: (tripId: string) => void; onClose: () => void }) {
  const [detail, setDetail] = useState<PassengerDetail | null>(null);
  const [error, setError] = useState('');
  const [noteBody, setNoteBody] = useState('');
  const [savingNote, setSavingNote] = useState(false);
  const [showChat, setShowChat] = useState(false);
  const [callSessionId, setCallSessionId] = useState('');

  const reload = () => apiGet<{ detail: PassengerDetail }>('passenger/detail.php', { key: passengerKey }).then(response => { setDetail(response.detail); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Passenger not found.'));
  useEffect(() => { void reload(); }, [passengerKey]);

  // "coord:{customer_id}" for a real Uthenga customer this driver has
  // carried; "walkin:{name}" for a walk-in with no account — nothing to
  // message or call in-app for those, only whatever phone they gave.
  const passengerCustomerId = detail?.passenger.source === 'coordination' && detail.passenger.passenger_key.startsWith('coord:')
    ? detail.passenger.passenger_key.slice('coord:'.length) : null;

  const submitNote = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!noteBody.trim()) return;
    setSavingNote(true);
    try {
      const response = await apiPost<{ result: PassengerDetail }>('passenger/action.php', { action: 'add_note', passenger_key: passengerKey, body: noteBody }, csrf);
      setDetail(response.result); setNoteBody('');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not save that note.'); }
    finally { setSavingNote(false); }
  };

  return <div className="dqx-drawer-backdrop" onMouseDown={onClose}><aside className="dqx-drawer" onMouseDown={event => event.stopPropagation()}>
    <header><h3>{detail ? detail.passenger.passenger_name : 'Passenger'}</h3><button onClick={onClose}><X /></button></header>
    {error && <p className="dqx-error">{error}</p>}
    {!detail && !error && <p className="dqx-muted">Loading passenger…</p>}
    {detail && <div className="dqx-drawer__body">
      <PassengerStatusChip status={detail.passenger.status} />
      <dl className="dqx-detail-list">
        <div><dt>Phone</dt><dd>{detail.passenger.passenger_phone || '—'}</dd></div>
        <div><dt>Total Trips</dt><dd>{detail.passenger.trip_count}</dd></div>
        <div><dt>Completed</dt><dd>{detail.passenger.completed_count}</dd></div>
        <div><dt>Cancelled</dt><dd>{detail.passenger.cancelled_count}</dd></div>
        <div><dt>Last Trip</dt><dd>{detail.passenger.last_trip_at ? dateTimeOf(detail.passenger.last_trip_at) : '—'}</dd></div>
      </dl>

      {detail.current_trip && <article className="dqx-card dqx-current-trip">
        <header><b>CURRENT TRIP</b></header>
        <div className="dqx-next__route"><MapPin />{detail.current_trip.pickup_location}<span>→</span>{detail.current_trip.destination_location}</div>
        <p><StatusChip status={detail.current_trip.status} /></p>
      </article>}

      <div className="dqx-drawer__actions">
        {detail.current_trip && detail.passenger.source !== 'coordination' && <button className="dqx-btn dqx-btn--ghost" onClick={() => onSelectTrip(detail.current_trip!.id)}>Open Trip</button>}
        {detail.passenger.active_session_id && <button className="dqx-btn dqx-btn--primary dqx-btn--sm" onClick={() => setCallSessionId(detail.passenger.active_session_id!)}><Phone /> Call</button>}
        {detail.passenger.passenger_phone && <PhoneContactActions phone={detail.passenger.passenger_phone} />}
        {(detail.passenger.active_session_id || passengerCustomerId) && <button className="dqx-btn dqx-btn--ghost" onClick={() => setShowChat(current => !current)}><MessageSquare /> {showChat ? 'Hide Messages' : 'Message'}</button>}
      </div>

      {showChat && (detail.passenger.active_session_id
        ? <CoordinationChatPanel boot={boot} sessionId={detail.passenger.active_session_id} hasActiveTrip={false} />
        : passengerCustomerId && <PersistentMessagePanel boot={boot} customerId={passengerCustomerId} />)}
      {callSessionId && <CallDrawer boot={boot} sessionId={callSessionId} onClose={() => setCallSessionId('')} />}

      {detail.previous_issue && <article className="dqx-card dqx-issue-card">
        <header><AlertTriangle /><h3>Previous trip issue</h3></header>
        <p>Trip {detail.previous_issue.trip_code}: {detail.previous_issue.reason}</p>
      </article>}

      <h4>Trip History</h4>
      <ul className="dqx-history-list">
        {detail.history.length === 0 && <li className="dqx-muted">No trips yet.</li>}
        {detail.history.map(trip => <li key={trip.id} className={detail.passenger.source === 'coordination' ? 'is-static' : ''} onClick={() => detail.passenger.source !== 'coordination' && onSelectTrip(trip.id)}>
          <div><b>{trip.trip_code}</b><span>{dateTimeOf(trip.requested_at)}</span></div>
          <div className="dqx-history-route">{trip.pickup_location} → {trip.destination_location}</div>
          <div><StatusChip status={trip.status} /><b>{money(trip.fare)}</b></div>
        </li>)}
      </ul>

      <h4>Driver Notes</h4>
      <form className="dqx-note-form" onSubmit={submitNote}>
        <textarea rows={2} value={noteBody} onChange={event => setNoteBody(event.target.value)} placeholder="Add a note about this passenger…" maxLength={1000} />
        <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={savingNote || !noteBody.trim()}><Plus /> Add Note</button>
      </form>
      <ul className="dqx-notes-list">
        {detail.notes.length === 0 && <li className="dqx-muted">No notes yet.</li>}
        {detail.notes.map(note => <li key={note.id}><p>{note.body}</p><small>{dateTimeOf(note.created_at)}</small></li>)}
      </ul>
    </div>}
  </aside></div>;
}

type UnifiedMessageItem = {
  key: string; kind: 'passenger' | 'system' | 'coordination' | 'direct' | 'direct-new'; title: string; subtitle: string;
  timestamp: string | null; unread: boolean; tripId: string; notificationId?: number; sessionId?: string; threadId?: string; customerId?: string;
};
const MESSAGE_CATEGORIES: { id: 'all' | 'passengers' | 'system'; label: string }[] = [
  { id: 'all', label: 'All' }, { id: 'passengers', label: 'Passengers' }, { id: 'system', label: 'System' },
];

// Every real passenger this driver can actually message lives in ONE unified
// list here — a live coordination session while one is active, otherwise
// their persistent direct-message thread (started on demand from a contact
// entry the first time). Historical tie_trips conversations have no
// authenticated passenger behind them at all, so they stay Call-only —
// that's an honest limitation, not a missing feature.
function MessagesWorkspace({ boot, onSelectTrip, refreshKey, hasActiveTrip }: { boot: Boot; onSelectTrip: (tripId: string) => void; refreshKey: number; hasActiveTrip: boolean }) {
  const [category, setCategory] = useState<'all' | 'passengers' | 'system'>('all');
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [notifications, setNotifications] = useState<TripNotification[]>([]);
  const [liveSessions, setLiveSessions] = useState<CoordinationQueueSession[]>([]);
  const [directThreads, setDirectThreads] = useState<DirectThreadSummary[]>([]);
  const [contacts, setContacts] = useState<KnownPassenger[]>([]);
  const [selected, setSelected] = useState<UnifiedMessageItem | null>(null);
  const [error, setError] = useState('');
  const [startingThread, setStartingThread] = useState(false);

  const load = () => Promise.all([
    apiGet<{ conversations: { conversations: Conversation[] } }>('message/conversations.php'),
    apiGet<{ notifications: { notifications: TripNotification[] } }>('message/notifications.php'),
    apiGet<{ coordination: { sessions: CoordinationQueueSession[] } }>('coordination/vendor-queue.php').catch(() => ({ coordination: { sessions: [] } })),
    apiGet<{ result: { threads: DirectThreadSummary[] } }>('direct-message/inbox.php').catch(() => ({ result: { threads: [] } })),
    apiGet<{ result: { passengers: KnownPassenger[] } }>('direct-message/contacts.php').catch(() => ({ result: { passengers: [] } })),
  ]).then(([c, n, coord, inbox, contactsResponse]) => {
    setConversations(c.conversations.conversations); setNotifications(n.notifications.notifications);
    setLiveSessions(coord.coordination.sessions); setDirectThreads(inbox.result.threads); setContacts(contactsResponse.result.passengers);
    setError('');
  }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load messages.'));

  useEffect(() => { void load(); const interval = window.setInterval(load, 6_000); return () => window.clearInterval(interval); }, [refreshKey]);

  const items: UnifiedMessageItem[] = useMemo(() => {
    const fromConversations: UnifiedMessageItem[] = conversations.map(c => ({ key: `c-${c.trip_id}`, kind: 'passenger', title: c.passenger_name, subtitle: c.summary, timestamp: c.last_activity_at, unread: false, tripId: c.trip_id }));
    const fromCoordination: UnifiedMessageItem[] = liveSessions.map(s => ({ key: `cs-${s.id}`, kind: 'coordination', title: s.customer_name || `${s.passenger_count} passenger${s.passenger_count === 1 ? '' : 's'} · LIVE`, subtitle: s.run ? `Loading at ${s.run.loading_location}` : 'Quick Taxi coordination request', timestamp: s.expires_at || null, unread: false, tripId: '', sessionId: s.id }));
    const liveNames = new Set(liveSessions.map(s => (s.customer_name || '').trim().toLowerCase()).filter(Boolean));
    const fromDirectThreads: UnifiedMessageItem[] = directThreads.map(t => ({ key: `dt-${t.id}`, kind: 'direct', title: t.peer_name, subtitle: t.last_message_preview || 'No messages yet', timestamp: t.last_message_at, unread: t.unread_count > 0, tripId: '', threadId: t.id }));
    const threadedNames = new Set(directThreads.map(t => t.peer_name.trim().toLowerCase()));
    const fromContacts: UnifiedMessageItem[] = contacts
      .filter(p => !threadedNames.has(p.name.trim().toLowerCase()) && !liveNames.has(p.name.trim().toLowerCase()))
      .map(p => ({ key: `dc-${p.customer_id}`, kind: 'direct-new', title: p.name, subtitle: p.last_session_at ? `Last rode ${dateTimeOf(p.last_session_at)}` : 'Message this passenger', timestamp: p.last_session_at, unread: false, tripId: '', customerId: p.customer_id }));
    const fromNotifications: UnifiedMessageItem[] = notifications.map(n => ({ key: `n-${n.id}`, kind: 'system', title: n.title, subtitle: n.reason || `Trip ${n.trip_code}`, timestamp: n.created_at, unread: !n.is_read, tripId: n.trip_id, notificationId: n.id }));
    const passengerItems = [...fromDirectThreads, ...fromCoordination, ...fromContacts, ...fromConversations];
    const merged = category === 'passengers' ? passengerItems : category === 'system' ? fromNotifications : [...passengerItems, ...fromNotifications];
    return merged.sort((a, b) => new Date(b.timestamp || 0).getTime() - new Date(a.timestamp || 0).getTime());
  }, [conversations, liveSessions, directThreads, contacts, notifications, category]);

  const unreadCount = notifications.filter(n => !n.is_read).length;
  const directUnreadCount = directThreads.reduce((total, t) => total + (t.unread_count > 0 ? 1 : 0), 0);

  const select = async (item: UnifiedMessageItem) => {
    if (item.kind === 'system' && item.unread && item.notificationId) {
      try { await apiPost('message/action.php', { action: 'mark_read', event_id: item.notificationId }, boot.csrf_token); void load(); } catch { /* best-effort; the panel already shows the content */ }
    }
    if (item.kind === 'direct-new' && item.customerId) {
      setStartingThread(true);
      try {
        const response = await apiPost<{ result: DirectThreadDetail }>('direct-message/action.php', { action: 'start', customer_id: item.customerId }, boot.csrf_token);
        setSelected({ ...item, kind: 'direct', threadId: response.result.thread_id });
        void load();
      } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not start this conversation.'); }
      finally { setStartingThread(false); }
      return;
    }
    setSelected(item);
  };

  const selectedConversation = selected?.kind === 'passenger' ? conversations.find(c => c.trip_id === selected.tripId) : undefined;
  const selectedNotification = selected?.kind === 'system' ? notifications.find(n => n.id === selected.notificationId) : undefined;
  const selectedCoordinationSessionId = selected?.kind === 'coordination' ? selected.sessionId : undefined;
  const selectedThreadId = selected?.kind === 'direct' ? selected.threadId : undefined;

  return <div className="dqx-messages">
    <header className="dqx-heading">
      <div><p className="dqx-eyebrow">QUICK TAXI · MESSAGES</p><h1>Messages</h1><span>Message any passenger you've carried, live or after the fact.</span></div>
      {unreadCount > 0 && <button className="dqx-btn dqx-btn--ghost" onClick={() => { void apiPost('message/action.php', { action: 'mark_all_read' }, boot.csrf_token).then(load); }}>Mark all read</button>}
    </header>

    <nav className="dqx-tabstrip">{MESSAGE_CATEGORIES.map(item => <button key={item.id} className={category === item.id ? 'is-active' : ''} onClick={() => setCategory(item.id)}>{item.label}{item.id === 'system' && unreadCount > 0 && <em>{unreadCount}</em>}{item.id === 'passengers' && directUnreadCount > 0 && <em>{directUnreadCount}</em>}</button>)}</nav>

    {error && <p className="dqx-error">{error}</p>}

    <div className="dqx-messages__layout">
      <div className="dqx-messages__list">
        {items.length === 0 && <div className="dqx-empty"><p>No messages yet.</p></div>}
        {items.map(item => <button key={item.key} className={`dqx-message-row ${selected?.key === item.key ? 'is-active' : ''}`} disabled={startingThread} onClick={() => void select(item)}>
          <span className="dqx-message-row__title">{item.unread && <i />}{item.title}</span>
          <span className="dqx-message-row__subtitle">{item.subtitle}</span>
          <span className="dqx-message-row__time">{item.timestamp ? dateTimeOf(item.timestamp) : ''}</span>
        </button>)}
      </div>
      <div className="dqx-messages__panel">
        {selectedConversation && <div className="dqx-message-context">
          <header><b>{selectedConversation.passenger_name}</b><small>Passenger · Trip {selectedConversation.trip_code}</small></header>
          <StatusChip status={selectedConversation.status} />
          <p className="dqx-muted">This trip predates their Uthenga account, so there's no in-app messaging for it — call the number they gave instead.</p>
          <dl className="dqx-detail-list">
            <div><dt>Route</dt><dd>{selectedConversation.summary}</dd></div>
            {selectedConversation.passenger_phone && <div><dt>Phone</dt><dd>{selectedConversation.passenger_phone}</dd></div>}
          </dl>
          <div className="dqx-drawer__actions">
            <button className="dqx-btn dqx-btn--ghost" onClick={() => onSelectTrip(selectedConversation.trip_id)}>Open Trip</button>
            {selectedConversation.passenger_phone && <PhoneContactActions phone={selectedConversation.passenger_phone} />}
          </div>
        </div>}
        {selectedNotification && <div className="dqx-message-context">
          <header><b>{selectedNotification.title}</b><small>System · Trip {selectedNotification.trip_code}</small></header>
          {selectedNotification.reason && <p className="dqx-muted">{selectedNotification.reason}</p>}
          <div className="dqx-drawer__actions"><button className="dqx-btn dqx-btn--ghost" onClick={() => onSelectTrip(selectedNotification.trip_id)}>Open Trip</button></div>
        </div>}
        {selectedCoordinationSessionId && <CoordinationChatPanel boot={boot} sessionId={selectedCoordinationSessionId} hasActiveTrip={hasActiveTrip} />}
        {selectedThreadId && <DirectThreadPanel boot={boot} threadId={selectedThreadId} onSent={load} />}
        {startingThread && <p className="dqx-muted">Opening conversation…</p>}
        {!selectedConversation && !selectedNotification && !selectedCoordinationSessionId && !selectedThreadId && !startingThread && <div className="dqx-empty"><p>Select a conversation to view details.</p></div>}
      </div>
    </div>
  </div>;
}

type DirectThreadSummary = { id: string; peer_name: string; last_message_at: string | null; last_message_preview: string | null; unread_count: number };
type DirectMessageItem = { id: string; sender_role: 'vendor' | 'customer'; body: string; created_at: string };
type DirectThreadDetail = { thread_id: string; viewer_role: 'vendor' | 'customer'; peer_name: string; messages: DirectMessageItem[] };
type KnownPassenger = { customer_id: string; name: string; last_session_at: string | null };

function DirectThreadPanel({ boot, threadId, onSent }: { boot: Boot; threadId: string; onSent: () => void }) {
  const [detail, setDetail] = useState<DirectThreadDetail | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const historyRef = useRef<HTMLDivElement | null>(null);

  const load = () => apiGet<{ result: DirectThreadDetail }>('direct-message/thread.php', { thread_id: threadId })
    .then(response => { setDetail(response.result); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'This conversation is unavailable.'));

  useEffect(() => {
    void load();
    void apiPost('direct-message/action.php', { action: 'mark_read', thread_id: threadId }, boot.csrf_token).catch(() => undefined);
    const interval = window.setInterval(load, 4_000);
    return () => window.clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [threadId]);
  useEffect(() => { if (historyRef.current) historyRef.current.scrollTop = historyRef.current.scrollHeight; }, [detail?.messages.length]);

  const send = async () => {
    const body = message.trim(); if (!body) return; setMessage('');
    try { await apiPost('direct-message/action.php', { action: 'send', thread_id: threadId, body }, boot.csrf_token); await load(); onSent(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Message could not be sent.'); }
  };

  return <div className="dqx-message-context">
    <header><b>{detail?.peer_name || 'Conversation'}</b><small>Direct message · not tied to a single trip</small></header>
    {error && <p className="dqx-error">{error}</p>}
    <div className="dqx-coord-chat" ref={historyRef}>
      {detail && detail.messages.length === 0 && <p className="dqx-muted">No messages yet.</p>}
      {detail?.messages.map(m => <p key={m.id} className={`dqx-coord-bubble ${m.sender_role === 'vendor' ? 'is-mine' : ''}`}><span>{m.body}</span><time>{new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></p>)}
    </div>
    <div className="dqx-coord-chat__input"><input value={message} maxLength={1000} placeholder={`Message ${detail?.peer_name || 'passenger'}`} onChange={event => setMessage(event.target.value)} onKeyDown={event => event.key === 'Enter' && void send()} /><button className="dqx-btn dqx-btn--primary dqx-btn--sm" onClick={() => void send()}>Send</button></div>
  </div>;
}

// Resolves (or opens) the persistent 1-1 thread for a known customer_id, then
// renders it — used wherever a passenger is reachable but has no live
// coordination session right now (e.g. from their profile in Passengers tab).
function PersistentMessagePanel({ boot, customerId }: { boot: Boot; customerId: string }) {
  const [threadId, setThreadId] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    apiPost<{ result: DirectThreadDetail }>('direct-message/action.php', { action: 'start', customer_id: customerId }, boot.csrf_token)
      .then(response => setThreadId(response.result.thread_id))
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not open this conversation.'));
  }, [customerId, boot.csrf_token]);

  if (error) return <p className="dqx-error">{error}</p>;
  if (!threadId) return <p className="dqx-muted">Connecting…</p>;
  return <DirectThreadPanel boot={boot} threadId={threadId} onSent={() => undefined} />;
}

type CallPhase = 'idle' | 'connecting' | 'connected' | 'failed';
const ICE_SERVERS: RTCIceServer[] = [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];
const DRIVING_AUTO_REPLY = "I'm currently driving. I'll contact you when safe.";
function formatCallClock(totalSeconds: number): string { return `${String(Math.floor(totalSeconds / 60)).padStart(2, '0')}:${String(totalSeconds % 60).padStart(2, '0')}`; }

// Uthenga-mediated in-app audio calling: no phone number is ever exchanged.
// The call itself flows peer-to-peer over WebRTC once both sides accept;
// Uthenga only relays signaling (offer/answer/ICE) and never sees the media.
function CallPanel({ boot, sessionId, call, allowedActions, viewerRole, peerLabel, hasActiveTrip, autoStart, onRefresh }: {
  boot: Boot; sessionId: string; call: CallState; allowedActions: string[]; viewerRole: 'customer' | 'vendor';
  peerLabel: string; hasActiveTrip?: boolean; autoStart?: boolean; onRefresh: () => void;
}) {
  const pcRef = useRef<RTCPeerConnection | null>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const remoteAudioRef = useRef<HTMLAudioElement | null>(null);
  const roleForCallRef = useRef<Map<string, 'caller' | 'callee'>>(new Map());
  const negotiatedRef = useRef<string | null>(null);
  const pendingIceRef = useRef<RTCIceCandidateInit[]>([]);
  const sinceIdRef = useRef(0);
  const [phase, setPhase] = useState<CallPhase>('idle');
  const [micError, setMicError] = useState('');
  const [dismissed, setDismissed] = useState(false);
  const [elapsed, setElapsed] = useState(0);
  const [busy, setBusy] = useState(false);

  const teardown = () => {
    pcRef.current?.close(); pcRef.current = null;
    localStreamRef.current?.getTracks().forEach(track => track.stop()); localStreamRef.current = null;
    sinceIdRef.current = 0; negotiatedRef.current = null; pendingIceRef.current = [];
    setPhase('idle'); setMicError('');
  };
  useEffect(() => () => teardown(), []);
  useEffect(() => { setDismissed(false); setElapsed(0); }, [call.call_request_id]);

  useEffect(() => {
    if (!call.call_request_id) return;
    if (call.state === 'RINGING_OUT' && !roleForCallRef.current.has(call.call_request_id)) roleForCallRef.current.set(call.call_request_id, 'caller');
    if (call.state === 'RINGING_IN' && !roleForCallRef.current.has(call.call_request_id)) roleForCallRef.current.set(call.call_request_id, 'callee');
  }, [call.state, call.call_request_id]);

  const postSignal = async (kind: 'offer' | 'answer' | 'ice' | 'hangup', payload: Record<string, unknown>) => {
    if (!call.call_request_id) return;
    try { await apiPost('coordination/action.php', { action: 'call_signal', call_request_id: call.call_request_id, kind, payload }, boot.csrf_token); }
    catch { /* best-effort; the peer notices via connection state or the next poll */ }
  };

  const flushPendingIce = async (pc: RTCPeerConnection) => {
    const queued = pendingIceRef.current; pendingIceRef.current = [];
    for (const candidate of queued) await pc.addIceCandidate(new RTCIceCandidate(candidate)).catch(() => undefined);
  };

  const ensurePeerConnection = async (): Promise<RTCPeerConnection> => {
    if (pcRef.current) return pcRef.current;
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    pc.onicecandidate = event => { if (event.candidate) void postSignal('ice', event.candidate.toJSON() as unknown as Record<string, unknown>); };
    pc.ontrack = event => { if (remoteAudioRef.current) remoteAudioRef.current.srcObject = event.streams[0]; };
    pc.onconnectionstatechange = () => {
      if (pc.connectionState === 'connected') setPhase('connected');
      else if (pc.connectionState === 'failed') setPhase('failed');
    };
    pcRef.current = pc;
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    localStreamRef.current = stream;
    stream.getTracks().forEach(track => pc.addTrack(track, stream));
    return pc;
  };

  const handleSignal = async (signal: CallSignal) => {
    if (signal.sender_role === viewerRole) return;
    const pc = pcRef.current; if (!pc) return;
    if (signal.kind === 'offer') {
      await pc.setRemoteDescription(new RTCSessionDescription(signal.payload)); await flushPendingIce(pc);
      const answer = await pc.createAnswer(); await pc.setLocalDescription(answer);
      await postSignal('answer', { type: answer.type, sdp: answer.sdp });
    } else if (signal.kind === 'answer') {
      await pc.setRemoteDescription(new RTCSessionDescription(signal.payload)); await flushPendingIce(pc);
    } else if (signal.kind === 'ice') {
      if (pc.remoteDescription) await pc.addIceCandidate(new RTCIceCandidate(signal.payload)).catch(() => undefined);
      else pendingIceRef.current.push(signal.payload as RTCIceCandidateInit);
    } else if (signal.kind === 'hangup') { teardown(); onRefresh(); }
  };

  // Negotiate once (and only once per call_request_id) after the call reaches ACCEPTED.
  useEffect(() => {
    if (call.state !== 'ACCEPTED' || !call.call_request_id) return;
    if (negotiatedRef.current === call.call_request_id) return;
    negotiatedRef.current = call.call_request_id;
    setPhase('connecting'); setMicError('');
    const role = roleForCallRef.current.get(call.call_request_id) || 'callee';
    void (async () => {
      try {
        const pc = await ensurePeerConnection();
        if (role === 'caller') {
          const offer = await pc.createOffer(); await pc.setLocalDescription(offer);
          await postSignal('offer', { type: offer.type, sdp: offer.sdp });
        }
      } catch { setMicError('Microphone access is required for in-app calls. Enable it and try again.'); setPhase('failed'); }
    })();
    const failTimeout = window.setTimeout(() => setPhase(current => current === 'connected' ? current : 'failed'), 15_000);
    return () => window.clearTimeout(failTimeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [call.state, call.call_request_id]);

  // Fast signaling poll only while a call is connecting/active — never on the idle/ringing baseline.
  useEffect(() => {
    if (call.state !== 'ACCEPTED' || !call.call_request_id) return;
    let disposed = false;
    const poll = async () => {
      try {
        const response = await apiGet<{ result: { signals: CallSignal[] } }>('coordination/call-signals.php', { call_request_id: call.call_request_id!, since_id: String(sinceIdRef.current) });
        for (const signal of response.result.signals) { sinceIdRef.current = Math.max(sinceIdRef.current, signal.id); if (!disposed) await handleSignal(signal); }
      } catch { /* transient network hiccup; retried next tick */ }
    };
    void poll();
    const interval = window.setInterval(poll, 1200);
    return () => { disposed = true; window.clearInterval(interval); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [call.state, call.call_request_id]);

  useEffect(() => {
    if (call.state === 'NONE' || call.state === 'DECLINED' || call.state === 'CANCELLED' || call.state === 'ENDED') teardown();
    if (call.state === 'DECLINED' || call.state === 'CANCELLED' || call.state === 'ENDED') { const t = window.setTimeout(() => setDismissed(true), 4_000); return () => window.clearTimeout(t); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [call.state, call.call_request_id]);

  useEffect(() => {
    if (phase !== 'connected') return;
    const startedAt = Date.now();
    const interval = window.setInterval(() => setElapsed(Math.floor((Date.now() - startedAt) / 1000)), 1_000);
    return () => window.clearInterval(interval);
  }, [phase, call.call_request_id]);

  const startCall = async () => {
    setBusy(true);
    try { await apiPost('coordination/action.php', { action: 'request_call', session_id: sessionId }, boot.csrf_token); onRefresh(); }
    catch { /* surfaced by the parent panel's own error state */ } finally { setBusy(false); }
  };
  const decide = async (decision: 'ACCEPT' | 'DECLINE') => {
    if (!call.call_request_id) return; setBusy(true);
    try { await apiPost('coordination/action.php', { action: 'decide_call', call_request_id: call.call_request_id, decision }, boot.csrf_token); onRefresh(); }
    catch { /* best-effort */ } finally { setBusy(false); }
  };
  const hangup = async () => { if (call.call_request_id) await postSignal('hangup', {}); teardown(); onRefresh(); };
  const sendAutomatedReply = async () => {
    if (!call.call_request_id) return; setBusy(true);
    try {
      await apiPost('coordination/action.php', { action: 'decide_call', call_request_id: call.call_request_id, decision: 'DECLINE' }, boot.csrf_token);
      await apiPost('coordination/action.php', { action: 'message', session_id: sessionId, body: DRIVING_AUTO_REPLY }, boot.csrf_token);
      onRefresh();
    } catch { /* best-effort */ } finally { setBusy(false); }
  };

  const autoStartedRef = useRef(false);
  useEffect(() => {
    if (!autoStart || autoStartedRef.current) return;
    if (call.state === 'NONE' && allowedActions.includes('REQUEST_CALL')) { autoStartedRef.current = true; void startCall(); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoStart, call.state]);

  if (call.state === 'NONE' || dismissed) {
    if (!allowedActions.includes('REQUEST_CALL')) return null;
    return <button className="dqx-btn dqx-btn--ghost dqx-btn--sm dqx-call-start" disabled={busy} onClick={() => void startCall()}><Phone /> Call {peerLabel}</button>;
  }

  return <div className="dqx-call-panel">
    <audio ref={remoteAudioRef} autoPlay />
    {call.state === 'RINGING_OUT' && <>
      <div className="dqx-call-panel__status"><PhoneOutgoing /><b>Calling {call.peer_name || peerLabel}…</b><small>Waiting for an answer</small></div>
      <button className="dqx-btn dqx-btn--danger dqx-btn--sm" onClick={() => void hangup()}><PhoneOff /> Cancel</button>
    </>}
    {call.state === 'RINGING_IN' && <>
      <div className="dqx-call-panel__status"><PhoneIncoming /><b>Incoming call from {call.peer_name || peerLabel}</b></div>
      <div className="dqx-call-panel__row">
        <button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy} onClick={() => void decide('ACCEPT')}><Phone /> Answer</button>
        <button className="dqx-btn dqx-btn--danger dqx-btn--sm" disabled={busy} onClick={() => void decide('DECLINE')}><PhoneOff /> Decline</button>
      </div>
      {hasActiveTrip && <div className="dqx-call-panel__driving">
        <span>You're on a trip.</span>
        <div className="dqx-call-panel__row">
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => void decide('ACCEPT')}>Quick Answer</button>
          <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => void sendAutomatedReply()}>Send Automated Message</button>
        </div>
      </div>}
    </>}
    {call.state === 'ACCEPTED' && <>
      <div className="dqx-call-panel__status">
        {phase === 'failed'
          ? <><PhoneOff /><b>Call could not connect</b><small>{micError || 'This network could not establish a direct audio connection.'}</small></>
          : <><Phone /><b>{call.peer_name || peerLabel}</b><small>{phase === 'connected' ? formatCallClock(elapsed) : 'Connecting…'}</small></>}
      </div>
      <button className="dqx-btn dqx-btn--danger dqx-btn--sm" onClick={() => void hangup()}><PhoneOff /> Hang Up</button>
    </>}
    {(call.state === 'DECLINED' || call.state === 'CANCELLED' || call.state === 'ENDED') && <div className="dqx-call-panel__status dqx-call-panel__status--ended"><PhoneOff /><b>{call.state === 'DECLINED' ? 'Call declined' : call.state === 'CANCELLED' ? 'Call cancelled' : 'Call ended'}</b></div>}
  </div>;
}

function CoordinationChatPanel({ boot, sessionId, hasActiveTrip }: { boot: Boot; sessionId: string; hasActiveTrip: boolean }) {
  const [detail, setDetail] = useState<CoordinationSessionDetail | null>(null);
  const [pending, setPending] = useState<CoordinationMessage[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const historyRef = useRef<HTMLDivElement | null>(null);

  const load = () => apiGet<{ coordination: CoordinationSessionDetail }>('coordination/session.php', { session_id: sessionId })
    .then(response => {
      setDetail(response.coordination); setError('');
      setPending(current => current.filter(item => !response.coordination.messages.some(m => m.sender_role === item.sender_role && m.body === item.body)));
    })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'This live session is unavailable.'));

  useEffect(() => { void load(); const interval = window.setInterval(load, 3_000); return () => window.clearInterval(interval); }, [sessionId]);
  const allMessages = useMemo(() => [...(detail?.messages || []), ...pending], [detail, pending]);
  useEffect(() => { if (historyRef.current) historyRef.current.scrollTop = historyRef.current.scrollHeight; }, [allMessages.length]);

  const send = async () => {
    const body = message.trim();
    if (!body || !detail) return;
    const optimistic = { id: `pending-${Date.now()}`, sender_role: detail.viewer_role, body, created_at: new Date().toISOString() };
    setPending(current => [...current, optimistic]); setMessage('');
    try { await apiPost('coordination/action.php', { action: 'message', session_id: sessionId, body }, boot.csrf_token); await load(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Message could not be sent.'); setPending(current => current.filter(item => item.id !== optimistic.id)); }
  };

  const canMessage = detail?.workspace.allowed_actions.includes('MESSAGE');

  return <div className="dqx-message-context">
    <header><b>Live Passenger Chat</b><small>{detail?.session.run ? `Loading at ${detail.session.run.loading_location}` : 'Quick Taxi coordination request'}</small></header>
    {error && <p className="dqx-error">{error}</p>}
    {detail && <CallPanel boot={boot} sessionId={sessionId} call={detail.call} allowedActions={detail.workspace.allowed_actions} viewerRole={detail.viewer_role} peerLabel="Passenger" hasActiveTrip={hasActiveTrip} onRefresh={() => void load()} />}
    {detail && !canMessage && <p className="dqx-muted">Accept this request to start messaging with the passenger.</p>}
    {detail && canMessage && <>
      <div className="dqx-coord-chat" ref={historyRef}>
        {allMessages.length === 0 && <p className="dqx-muted">No messages yet.</p>}
        {allMessages.map(item => <p key={item.id} className={`dqx-coord-bubble ${item.sender_role === detail.viewer_role ? 'is-mine' : ''}`}><span>{item.body}</span><time>{new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></p>)}
      </div>
      <div className="dqx-coord-chat__input"><input value={message} maxLength={1000} placeholder="Message passenger" onChange={event => setMessage(event.target.value)} onKeyDown={event => event.key === 'Enter' && void send()} /><button className="dqx-btn dqx-btn--primary dqx-btn--sm" onClick={() => void send()}>Send</button></div>
    </>}
  </div>;
}

const EARNINGS_PERIODS: { id: string; label: string }[] = [{ id: 'today', label: 'Today' }, { id: 'week', label: 'This Week' }, { id: 'month', label: 'This Month' }];

function exportEarningsCsv(period: string, transactions: EarningsTransaction[]) {
  const headers = ['Trip', 'Completed', 'Route', 'Fare', 'Payment'];
  const rows = transactions.map(t => [t.trip_code, t.completed_at ? dateTimeOf(t.completed_at) : '', t.route, String(t.fare), t.payment_method || '']);
  const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a'); link.href = url; link.download = `uthenga-earnings-${period}-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link); link.click(); document.body.removeChild(link); URL.revokeObjectURL(url);
}

function EarningsWorkspace({ boot, onSelectTrip, refreshKey }: { boot: Boot; onSelectTrip: (tripId: string) => void; refreshKey: number }) {
  const [period, setPeriod] = useState('today');
  const [summary, setSummary] = useState<EarningsSummary | null>(null);
  const [trend, setTrend] = useState<EarningsTrendPoint[]>([]);
  const [transactions, setTransactions] = useState<EarningsTransaction[]>([]);
  const [goal, setGoal] = useState<EarningsGoal | null>(null);
  const [editingGoal, setEditingGoal] = useState(false);
  const [goalInput, setGoalInput] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const loadPeriodData = () => Promise.all([
    apiGet<{ summary: EarningsSummary }>('earnings/summary.php', { period }),
    apiGet<{ transactions: { transactions: EarningsTransaction[] } }>('earnings/transactions.php', { period }),
  ]).then(([s, t]) => { setSummary(s.summary); setTransactions(t.transactions.transactions); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load earnings.'));
  const loadTrend = () => apiGet<{ trend: { series: EarningsTrendPoint[] } }>('earnings/trend.php', { days: '14' }).then(response => setTrend(response.trend.series)).catch(() => undefined);
  const loadGoal = () => apiGet<{ goal: EarningsGoal }>('earnings/goal.php').then(response => setGoal(response.goal)).catch(() => undefined);

  useEffect(() => { void loadPeriodData(); }, [period, refreshKey]);
  useEffect(() => { void loadTrend(); void loadGoal(); }, [refreshKey]);

  const saveGoal = async () => {
    if (!goalInput) return;
    setBusy(true);
    try { const response = await apiPost<{ result: EarningsGoal }>('earnings/action.php', { action: 'set_goal', weekly_goal: Number(goalInput) }, boot.csrf_token); setGoal(response.result); setEditingGoal(false); setGoalInput(''); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not save that goal.'); }
    finally { setBusy(false); }
  };

  const maxTrend = Math.max(1, ...trend.map(point => point.earnings));
  const goalProgress = goal?.weekly_goal ? Math.min(100, (goal.week_earnings / goal.weekly_goal) * 100) : 0;
  const totalPayments = summary ? summary.digital_count + summary.cash_count : 0;

  return <div className="dqx-earnings">
    <header className="dqx-heading">
      <div><p className="dqx-eyebrow">QUICK TAXI · EARNINGS</p><h1>Earnings</h1><span>Track your trips, income and payment mix.</span></div>
      <div className="dqx-heading__actions"><button className="dqx-btn dqx-btn--ghost" onClick={() => exportEarningsCsv(period, transactions)}><Download /> Export</button></div>
    </header>

    <nav className="dqx-tabstrip">{EARNINGS_PERIODS.map(item => <button key={item.id} className={period === item.id ? 'is-active' : ''} onClick={() => setPeriod(item.id)}>{item.label}</button>)}</nav>

    {error && <p className="dqx-error">{error}</p>}

    {summary && <section className="dqx-kpis">
      <Kpi label="Trips" value={String(summary.trips)} />
      <Kpi label="Earnings" value={money(summary.earnings)} accent="green" />
      <Kpi label="Average Fare" value={money(summary.average_fare)} />
      <Kpi label="Distance" value={`${summary.distance_km.toFixed(1)} km`} />
      <Kpi label="Cancelled" value={String(summary.cancelled)} accent="amber" />
    </section>}

    <section className="dqx-grid">
      <article className="dqx-card dqx-earnings-trend">
        <header><BarChart3 /><h3>Earnings — last 14 days</h3></header>
        <div className="dqx-trend-chart">{trend.map(point => <div key={point.date} className="dqx-trend-bar" title={`${point.date}: ${money(point.earnings)}`}><div className="dqx-trend-bar__fill" style={{ height: `${Math.max(4, (point.earnings / maxTrend) * 100)}%` }} /></div>)}</div>
      </article>

      <article className="dqx-card">
        <header><WalletCards /><h3>Payment mix</h3></header>
        {totalPayments > 0 ? <div className="dqx-payment-mix">
          <div className="dqx-payment-mix__row"><span>Digital</span><div className="dqx-payment-mix__bar"><i style={{ width: `${(summary!.digital_count / totalPayments) * 100}%` }} /></div><b>{summary!.digital_count}</b></div>
          <div className="dqx-payment-mix__row"><span>Cash</span><div className="dqx-payment-mix__bar is-cash"><i style={{ width: `${(summary!.cash_count / totalPayments) * 100}%` }} /></div><b>{summary!.cash_count}</b></div>
        </div> : <p className="dqx-muted">No completed trips in this period yet.</p>}
      </article>

      <article className="dqx-card">
        <header><ReceiptText /><h3>Weekly Goal</h3></header>
        {goal?.weekly_goal ? <>
          <div className="dqx-goal-figures"><b>{money(goal.week_earnings)}</b><span>/ {money(goal.weekly_goal)}</span></div>
          <div className="dqx-progress"><i style={{ width: `${goalProgress}%` }} /></div>
          <p className="dqx-muted">{goal.week_earnings >= goal.weekly_goal ? 'Goal reached this week.' : `${money(goal.weekly_goal - goal.week_earnings)} remaining.`}</p>
        </> : <p className="dqx-muted">No weekly goal set yet.</p>}
        {!editingGoal
          ? <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => { setEditingGoal(true); setGoalInput(goal?.weekly_goal ? String(goal.weekly_goal) : ''); }}>{goal?.weekly_goal ? 'Edit Goal' : 'Set Goal'}</button>
          : <div className="dqx-cancel-inline"><input type="number" min="0" value={goalInput} onChange={event => setGoalInput(event.target.value)} placeholder="Weekly goal (MK)" /><button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy || !goalInput} onClick={() => void saveGoal()}>Save</button><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setEditingGoal(false)}>Cancel</button></div>}
      </article>
    </section>

    <h4 className="dqx-section-title">Trip Earnings</h4>
    <div className="dqx-trip-list">
      {transactions.length === 0 && <div className="dqx-empty"><p>No completed trips in this period.</p></div>}
      {transactions.map(transaction => { const isDeparture = transaction.source === 'departure'; return <article key={transaction.id} className={`dqx-trip-row ${isDeparture ? 'is-departure' : ''}`} onClick={() => !isDeparture && onSelectTrip(transaction.id)}>
        <div className="dqx-trip-row__field"><small>Trip</small><b>{transaction.trip_code}</b>{isDeparture && <span className="dqx-status-badge">Departure</span>}</div>
        <div className="dqx-trip-row__field"><small>Completed</small><b>{transaction.completed_at ? dateTimeOf(transaction.completed_at) : '—'}</b></div>
        <div className="dqx-trip-row__field dqx-trip-row__route"><small>Route</small><b><MapPin />{transaction.route}</b></div>
        <div className="dqx-trip-row__field"><small>Payment</small><b>{transaction.payment_method === 'digital' ? 'Digital' : transaction.payment_method === 'cash' ? 'Cash' : '—'}</b></div>
        <div className="dqx-trip-row__field"><small>Fare</small><b>{money(transaction.fare)}</b></div>
        {!isDeparture && <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={event => { event.stopPropagation(); onSelectTrip(transaction.id); }}>View</button>}
      </article>; })}
    </div>
  </div>;
}

const VEHICLE_DOCUMENT_TYPES = ['registration', 'insurance', 'roadworthiness', 'permit'];
const VEHICLE_DOCUMENT_LABELS: Record<string, string> = { registration: 'Registration', insurance: 'Insurance', roadworthiness: 'Roadworthiness', permit: 'Operating Permit' };
const DOCUMENT_STATUS_META: Record<string, { label: string; color: string }> = {
  valid: { label: 'Valid', color: '#10b981' }, expiring_soon: { label: 'Expiring soon', color: '#f59e0b' }, expired: { label: 'Expired', color: '#ef4444' },
};
const VEHICLE_ISSUE_CATEGORIES = ['brakes', 'engine', 'tyres', 'electrical', 'ac', 'body', 'other'];
const titleCase = (value: string) => value.length ? value[0].toUpperCase() + value.slice(1) : value;

function VehicleWorkspace({ boot, refreshKey }: { boot: Boot; refreshKey: number }) {
  const [overview, setOverview] = useState<VehicleOverview | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingProfile, setEditingProfile] = useState(false);
  const [editingMileage, setEditingMileage] = useState(false);
  const [mileageInput, setMileageInput] = useState('');
  const [showMaintenanceForm, setShowMaintenanceForm] = useState(false);
  const [showIssueForm, setShowIssueForm] = useState(false);

  const load = () => apiGet<{ overview: VehicleOverview }>('vehicle/overview.php').then(response => { setOverview(response.overview); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load your vehicle.'));
  useEffect(() => { void load(); }, [refreshKey]);

  const act = async (payload: Record<string, unknown>) => {
    setBusy(true); setError('');
    try { const response = await apiPost<{ result: VehicleOverview }>('vehicle/action.php', payload, boot.csrf_token); setOverview(response.result); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not complete that action.'); }
    finally { setBusy(false); }
  };

  if (!overview) return <div className="dqx-loading"><RefreshCw className="dqx-spin" /><p>Loading your vehicle…</p></div>;
  const { vehicle, documents, maintenance, open_issues, resolved_issues, activity, mileage_since_service } = overview;

  return <div className="dqx-vehicle">
    <header className="dqx-heading"><div><p className="dqx-eyebrow">QUICK TAXI · VEHICLE</p><h1>My Vehicle</h1><span>Your assigned Quick Taxi vehicle, documents and maintenance.</span></div></header>

    {error && <p className="dqx-error">{error}</p>}

    {(!vehicle || editingProfile) ? <article className="dqx-card">
      <header><Car /><h3>{vehicle ? 'Edit vehicle' : 'Set up your vehicle'}</h3></header>
      <VehicleProfileForm vehicle={vehicle} busy={busy} onSave={input => { void act({ action: 'save_profile', ...input }); setEditingProfile(false); }} onCancel={vehicle ? () => setEditingProfile(false) : undefined} />
    </article> : <>
      <article className={`dqx-hero ${vehicle.status === 'active' ? 'is-online' : 'is-offline'}`}>
        <div>
          <span className="dqx-hero__badge">{vehicle.status === 'active' ? '● OPERATIONAL' : '● INACTIVE'}</span>
          <h2>{vehicle.make_model}</h2>
          <p>Plate {vehicle.plate_number}{vehicle.colour ? ` · ${vehicle.colour}` : ''}{vehicle.category ? ` · ${titleCase(vehicle.category)}` : ''}</p>
          <p className="dqx-muted">Assigned {vehicle.assigned_at ? dateTimeOf(vehicle.assigned_at) : '—'}</p>
        </div>
        <div className="dqx-vehicle-hero__actions">
          <button className="dqx-btn dqx-btn--ghost" onClick={() => setEditingProfile(true)}>Edit</button>
          <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void act({ action: 'set_status', status: vehicle.status === 'active' ? 'inactive' : 'active' })}>{vehicle.status === 'active' ? 'Mark Inactive' : 'Mark Active'}</button>
        </div>
      </article>

      <section className="dqx-kpis">
        <Kpi label="Today's Distance" value={`${activity.today_distance_km.toFixed(1)} km`} />
        <Kpi label="Today's Trips" value={String(activity.today_trips)} />
        <Kpi label="Avg Trip Distance" value={`${activity.average_trip_distance_km.toFixed(1)} km`} />
        <Kpi label="Current Session" value={activity.current_session_minutes !== null ? `${activity.current_session_minutes} min` : 'Offline'} accent={activity.current_session_minutes !== null ? 'green' : undefined} />
      </section>

      <section className="dqx-grid">
        <article className="dqx-card">
          <header><ShieldCheck /><h3>Documents</h3></header>
          <div className="dqx-document-list">{VEHICLE_DOCUMENT_TYPES.map(type => <DocumentRow key={type} type={type} doc={documents.find(document => document.document_type === type)} busy={busy} onSave={(documentType, expiry) => void act({ action: 'save_document', document_type: documentType, expiry_date: expiry })} />)}</div>
        </article>

        <article className="dqx-card">
          <header><Gauge /><h3>Mileage</h3></header>
          <div className="dqx-mileage-figures"><b>{vehicle.current_mileage_km !== null ? `${vehicle.current_mileage_km.toLocaleString()} km` : 'Not recorded'}</b>{vehicle.mileage_updated_at && <span>Updated {dateTimeOf(vehicle.mileage_updated_at)}</span>}</div>
          {mileage_since_service !== null && <p className="dqx-muted">{mileage_since_service.toLocaleString()} km since last recorded service.</p>}
          {!editingMileage
            ? <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => { setEditingMileage(true); setMileageInput(vehicle.current_mileage_km !== null ? String(vehicle.current_mileage_km) : ''); }}>Update Mileage</button>
            : <div className="dqx-cancel-inline"><input type="number" min="0" value={mileageInput} onChange={event => setMileageInput(event.target.value)} placeholder="Current km" /><button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy || !mileageInput} onClick={() => { void act({ action: 'update_mileage', current_mileage_km: Number(mileageInput) }); setEditingMileage(false); }}>Save</button><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setEditingMileage(false)}>Cancel</button></div>}
        </article>
      </section>

      <section className="dqx-grid">
        <article className="dqx-card">
          <header><Wrench /><h3>Maintenance</h3><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setShowMaintenanceForm(true)}><Plus /> Log Service</button></header>
          <ul className="dqx-history-list">
            {maintenance.length === 0 && <li className="dqx-muted">No maintenance recorded yet.</li>}
            {maintenance.map(record => <li key={record.id}><div><b>{record.service_type}</b><span>{dateOnly(record.serviced_at)}</span></div>{record.mileage_km !== null && <div className="dqx-history-route">{record.mileage_km.toLocaleString()} km</div>}{record.notes && <div className="dqx-history-route">{record.notes}</div>}</li>)}
          </ul>
        </article>

        <article className="dqx-card">
          <header><AlertTriangle /><h3>Vehicle Issues</h3><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setShowIssueForm(true)}><Plus /> Report Issue</button></header>
          <ul className="dqx-history-list">
            {open_issues.length === 0 && <li className="dqx-muted">No open issues.</li>}
            {open_issues.map(issue => <li key={issue.id}><div><b>{titleCase(issue.category)}</b><span className={`dqx-severity is-${issue.severity}`}>{titleCase(issue.severity)}</span></div><div className="dqx-history-route">{issue.description}</div><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" disabled={busy} onClick={() => void act({ action: 'resolve_issue', issue_id: issue.id })}>Mark Resolved</button></li>)}
          </ul>
          {resolved_issues.length > 0 && <details className="dqx-activity"><summary>Resolved ({resolved_issues.length})</summary><ul>{resolved_issues.map(issue => <li key={issue.id}><b>{titleCase(issue.category)}</b><span>{issue.resolved_at ? dateTimeOf(issue.resolved_at) : ''}</span></li>)}</ul></details>}
        </article>
      </section>
    </>}

    {showMaintenanceForm && <MaintenanceModal busy={busy} onClose={() => setShowMaintenanceForm(false)} onCreate={input => { void act({ action: 'add_maintenance', ...input }); setShowMaintenanceForm(false); }} />}
    {showIssueForm && <IssueModal busy={busy} onClose={() => setShowIssueForm(false)} onCreate={input => { void act({ action: 'report_issue', ...input }); setShowIssueForm(false); }} />}
  </div>;
}

function VehicleProfileForm({ vehicle, busy, onSave, onCancel }: { vehicle: VehicleProfile | null; busy: boolean; onSave: (input: Record<string, unknown>) => void; onCancel?: () => void }) {
  const [form, setForm] = useState({ make_model: vehicle?.make_model || '', plate_number: vehicle?.plate_number || '', colour: vehicle?.colour || '', category: vehicle?.category || '', photo_url: vehicle?.photo_url || '' });
  const update = (key: keyof typeof form, value: string) => setForm(current => ({ ...current, [key]: value }));
  return <form className="dqx-vehicle-form" onSubmit={event => { event.preventDefault(); onSave(form); }}>
    <label>Make & model<input required value={form.make_model} onChange={event => update('make_model', event.target.value)} placeholder="Toyota Corolla" /></label>
    <label>Plate number<input required value={form.plate_number} onChange={event => update('plate_number', event.target.value)} placeholder="NA 1234" /></label>
    <label>Colour<input value={form.colour} onChange={event => update('colour', event.target.value)} placeholder="Optional" /></label>
    <label>Category<select value={form.category} onChange={event => update('category', event.target.value)}><option value="">Select…</option><option value="sedan">Sedan</option><option value="hatchback">Hatchback</option><option value="suv">SUV</option><option value="minivan">Minivan</option><option value="other">Other</option></select></label>
    <label>Photo URL<input value={form.photo_url} onChange={event => update('photo_url', event.target.value)} placeholder="Optional" /></label>
    <div className="dqx-vehicle-form__actions"><button className="dqx-btn dqx-btn--primary" disabled={busy}>{busy ? 'Saving…' : 'Save Vehicle'}</button>{onCancel && <button type="button" className="dqx-btn dqx-btn--ghost" onClick={onCancel}>Cancel</button>}</div>
  </form>;
}

function DocumentRow({ type, doc, busy, onSave }: { type: string; doc?: VehicleDocument; busy: boolean; onSave: (type: string, expiry: string) => void }) {
  const [editing, setEditing] = useState(false);
  const [date, setDate] = useState(doc?.expiry_date || '');
  const meta = doc ? DOCUMENT_STATUS_META[doc.status] : null;
  return <div className="dqx-document-row">
    <div className="dqx-document-row__info">
      <b>{VEHICLE_DOCUMENT_LABELS[type]}</b>
      {doc && meta ? <span className="dqx-chip" style={{ background: meta.color + '22', color: meta.color }}><i style={{ background: meta.color }} />{meta.label}</span> : <span className="dqx-muted">Not recorded</span>}
      {doc && <small>Expires {dateOnly(doc.expiry_date)}</small>}
    </div>
    {!editing
      ? <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setEditing(true)}>{doc ? 'Update' : 'Add'}</button>
      : <div className="dqx-cancel-inline"><input type="date" value={date} onChange={event => setDate(event.target.value)} /><button className="dqx-btn dqx-btn--primary dqx-btn--sm" disabled={busy || !date} onClick={() => { onSave(type, date); setEditing(false); }}>Save</button><button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setEditing(false)}>Cancel</button></div>}
  </div>;
}

function MaintenanceModal({ busy, onClose, onCreate }: { busy: boolean; onClose: () => void; onCreate: (input: Record<string, unknown>) => void }) {
  const [form, setForm] = useState({ service_type: '', serviced_at: new Date().toISOString().slice(0, 10), mileage_km: '', notes: '' });
  const update = (key: keyof typeof form, value: string) => setForm(current => ({ ...current, [key]: value }));
  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><form className="dqx-modal" onMouseDown={event => event.stopPropagation()} onSubmit={event => { event.preventDefault(); onCreate({ service_type: form.service_type, serviced_at: form.serviced_at, mileage_km: form.mileage_km || undefined, notes: form.notes || undefined }); }}>
    <header><h3>Log Service</h3><button type="button" onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <label>Service type<input required value={form.service_type} onChange={event => update('service_type', event.target.value)} placeholder="Oil & filter change" /></label>
      <label>Date<input type="date" required value={form.serviced_at} onChange={event => update('serviced_at', event.target.value)} /></label>
      <label>Mileage at service (km)<input type="number" min="0" value={form.mileage_km} onChange={event => update('mileage_km', event.target.value)} placeholder="Optional" /></label>
      <label>Notes<textarea rows={2} value={form.notes} onChange={event => update('notes', event.target.value)} placeholder="Optional" /></label>
    </div>
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy}>{busy ? 'Saving…' : 'Save Service Record'}</button></footer>
  </form></div>;
}

function IssueModal({ busy, onClose, onCreate }: { busy: boolean; onClose: () => void; onCreate: (input: Record<string, unknown>) => void }) {
  const [category, setCategory] = useState('brakes');
  const [description, setDescription] = useState('');
  const [severity, setSeverity] = useState<'low' | 'medium' | 'critical'>('low');
  return <div className="dqx-modal-backdrop" onMouseDown={onClose}><form className="dqx-modal" onMouseDown={event => event.stopPropagation()} onSubmit={event => { event.preventDefault(); if (!description.trim()) return; onCreate({ category, description, severity }); }}>
    <header><h3>Report Vehicle Issue</h3><button type="button" onClick={onClose}><X /></button></header>
    <div className="dqx-modal__body">
      <label>What's wrong?<select value={category} onChange={event => setCategory(event.target.value)}>{VEHICLE_ISSUE_CATEGORIES.map(item => <option key={item} value={item}>{titleCase(item)}</option>)}</select></label>
      <label>Description<textarea rows={3} required value={description} onChange={event => setDescription(event.target.value)} placeholder="Describe the issue…" /></label>
      <label>Severity<div className="dqx-segmented">{(['low', 'medium', 'critical'] as const).map(level => <button type="button" key={level} className={severity === level ? 'is-active' : ''} onClick={() => setSeverity(level)}>{titleCase(level)}</button>)}</div></label>
    </div>
    <footer><button className="dqx-btn dqx-btn--primary" disabled={busy || !description.trim()}>{busy ? 'Submitting…' : 'Submit Report'}</button></footer>
  </form></div>;
}

function durationLabel(minutes: number): string {
  const hours = Math.floor(minutes / 60); const rest = minutes % 60;
  return hours > 0 ? `${hours}h ${rest}m` : `${rest}m`;
}

function ScheduleWorkspace({ scheduleAct, refreshKey }: { scheduleAct: (payload: Record<string, unknown>) => Promise<any>; refreshKey: number }) {
  const [overview, setOverview] = useState<ScheduleOverview | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingAvailability, setEditingAvailability] = useState(false);
  const [draft, setDraft] = useState<AvailabilityDay[]>([]);

  const load = () => apiGet<{ overview: ScheduleOverview }>('schedule/overview.php').then(response => { setOverview(response.overview); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load your schedule.'));
  useEffect(() => { void load(); }, [refreshKey]);
  useEffect(() => { const interval = window.setInterval(load, 30_000); return () => window.clearInterval(interval); }, []);

  const toggleShift = async () => {
    setBusy(true); setError('');
    try { const result = await scheduleAct({ action: overview?.current_session ? 'end_shift' : 'start_shift' }); setOverview(result); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not update your shift.'); }
    finally { setBusy(false); }
  };

  const startEditingAvailability = () => { setDraft((overview?.availability || []).map(day => ({ ...day }))); setEditingAvailability(true); };
  const updateDraftDay = (index: number, patch: Partial<AvailabilityDay>) => setDraft(current => current.map((day, i) => i === index ? { ...day, ...patch } : day));
  const saveAvailability = async () => {
    setBusy(true); setError('');
    try {
      const days = draft.map(day => ({ day_of_week: day.day_of_week, is_off: day.is_off, start_time: day.start_time || '08:00', end_time: day.end_time || '17:00' }));
      const result = await scheduleAct({ action: 'save_availability', days }); setOverview(result); setEditingAvailability(false);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not save your availability.'); }
    finally { setBusy(false); }
  };

  if (!overview) return <div className="dqx-loading"><RefreshCw className="dqx-spin" /><p>Loading your schedule…</p></div>;

  return <div className="dqx-schedule">
    <header className="dqx-heading"><div><p className="dqx-eyebrow">QUICK TAXI · SCHEDULE</p><h1>My Schedule</h1><span>Manage your shifts and weekly availability.</span></div></header>

    {error && <p className="dqx-error">{error}</p>}

    <article className={`dqx-hero ${overview.current_session ? 'is-online' : 'is-offline'}`}>
      {overview.current_session ? <div>
        <span className="dqx-hero__badge">● ON SHIFT</span>
        <h2>Started {timeOf(overview.current_session.started_at)}</h2>
        <p>{durationLabel(overview.current_session.elapsed_minutes)} elapsed · {overview.current_session.trips_count} trips · {money(overview.current_session.earnings)}</p>
      </div> : <div>
        <span className="dqx-hero__badge">● OFF SHIFT</span>
        <h2>Not currently on shift</h2>
        <p>Starting a shift here is the same as going online from the Dashboard — it opens a session that tracks your trips and earnings until you end it.</p>
      </div>}
      <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void toggleShift()}>{overview.current_session ? 'End Shift' : 'Start Shift'}</button>
    </article>

    <article className="dqx-card">
      <header><Clock /><h3>Next Available</h3></header>
      {overview.next_available ? <div className="dqx-next"><div className="dqx-next__route"><CalendarCheck />{overview.next_available.label}, {dateOnly(overview.next_available.date)}</div><p>{overview.next_available.start_time} – {overview.next_available.end_time}</p></div> : <p className="dqx-muted">No upcoming availability set — add your weekly availability below.</p>}
    </article>

    <article className="dqx-card">
      <header><CalendarCheck /><h3>Weekly Availability</h3>{!editingAvailability && <button className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={startEditingAvailability}>Edit Availability</button>}</header>
      {!editingAvailability
        ? <ul className="dqx-availability-list">{overview.availability.map(day => <li key={day.day_of_week}><b>{day.label}</b><span>{day.is_off ? 'Unavailable' : `${day.start_time} – ${day.end_time}`}</span></li>)}</ul>
        : <div className="dqx-availability-editor">
            {draft.map((day, index) => <div key={day.day_of_week} className="dqx-availability-editor__row">
              <b>{day.label}</b>
              <label className="dqx-check"><input type="checkbox" checked={!day.is_off} onChange={event => updateDraftDay(index, { is_off: !event.target.checked })} /> Available</label>
              {!day.is_off && <><input type="time" value={day.start_time || '08:00'} onChange={event => updateDraftDay(index, { start_time: event.target.value })} /><span>–</span><input type="time" value={day.end_time || '17:00'} onChange={event => updateDraftDay(index, { end_time: event.target.value })} /></>}
            </div>)}
            <div className="dqx-vehicle-form__actions"><button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void saveAvailability()}>Save Availability</button><button className="dqx-btn dqx-btn--ghost" onClick={() => setEditingAvailability(false)}>Cancel</button></div>
          </div>}
    </article>

    <h4 className="dqx-section-title">Shift History</h4>
    <div className="dqx-trip-list">
      {overview.history.length === 0 && <div className="dqx-empty"><p>No completed shifts yet.</p></div>}
      {overview.history.map(session => <article key={session.id} className="dqx-trip-row">
        <div className="dqx-trip-row__field"><small>Started</small><b>{dateTimeOf(session.started_at)}</b></div>
        <div className="dqx-trip-row__field"><small>Ended</small><b>{session.ended_at ? dateTimeOf(session.ended_at) : '—'}</b></div>
        <div className="dqx-trip-row__field"><small>Duration</small><b>{durationLabel(session.duration_minutes)}</b></div>
        <div className="dqx-trip-row__field"><small>Trips</small><b>{session.trips_count}</b></div>
        <div className="dqx-trip-row__field"><small>Earnings</small><b>{money(session.earnings)}</b></div>
      </article>)}
    </div>
  </div>;
}

const REPORT_TYPES: { id: string; label: string }[] = [
  { id: 'trips', label: 'Trip Report' }, { id: 'earnings', label: 'Earnings Report' }, { id: 'shifts', label: 'Shift Report' }, { id: 'vehicle', label: 'Vehicle Activity' },
];
const REPORT_PRESETS: { id: string; label: string }[] = [{ id: 'week', label: 'This Week' }, { id: 'month', label: 'This Month' }, { id: 'last_month', label: 'Last Month' }];

function isoDate(date: Date): string { return date.toISOString().slice(0, 10); }
function presetRange(id: string): { start: string; end: string } {
  const now = new Date();
  if (id === 'month') return { start: isoDate(new Date(now.getFullYear(), now.getMonth(), 1)), end: isoDate(now) };
  if (id === 'last_month') return { start: isoDate(new Date(now.getFullYear(), now.getMonth() - 1, 1)), end: isoDate(new Date(now.getFullYear(), now.getMonth(), 0)) };
  const mondayOffset = (now.getDay() + 6) % 7;
  const monday = new Date(now); monday.setDate(now.getDate() - mondayOffset);
  return { start: isoDate(monday), end: isoDate(now) };
}

function reportExportRows(report: Report): { headers: string[]; rows: string[][] } {
  if (report.type === 'trips') return { headers: ['Trip', 'Requested', 'Route', 'Distance (km)', 'Duration (min)', 'Status', 'Fare', 'Payment'], rows: (report.rows as TripReportRow[]).map(r => [r.trip_code, dateTimeOf(r.requested_at), r.route, r.distance_km !== null ? String(r.distance_km) : '', r.duration_seconds !== null ? String(Math.round(r.duration_seconds / 60)) : '', r.status, r.fare !== null ? String(r.fare) : '', r.payment_method || '']) };
  if (report.type === 'earnings') return { headers: ['Trip', 'Completed', 'Route', 'Fare', 'Payment'], rows: (report.rows as EarningsReportRow[]).map(r => [r.trip_code, dateTimeOf(r.completed_at), r.route, String(r.fare), r.payment_method || '']) };
  if (report.type === 'shifts') return { headers: ['Started', 'Ended', 'Duration (min)', 'Trips', 'Earnings'], rows: (report.rows as ShiftReportRow[]).map(r => [dateTimeOf(r.started_at), r.ended_at ? dateTimeOf(r.ended_at) : '', r.duration_minutes !== null ? String(r.duration_minutes) : '', String(r.trips_count), String(r.earnings)]) };
  return { headers: ['Service', 'Date', 'Mileage (km)', 'Notes'], rows: (report.rows as VehicleReportRow[]).map(r => [r.service_type, dateOnly(r.serviced_at), r.mileage_km !== null ? String(r.mileage_km) : '', r.notes || '']) };
}
function exportReportCsv(report: Report) {
  const { headers, rows } = reportExportRows(report);
  const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a'); link.href = url; link.download = `uthenga-${report.type}-report-${report.range.start}-to-${report.range.end}.csv`;
  document.body.appendChild(link); link.click(); document.body.removeChild(link); URL.revokeObjectURL(url);
}

function ReportSummaryCards({ report }: { report: Report }) {
  const s = report.summary;
  if (report.type === 'trips') return <section className="dqx-kpis">
    <Kpi label="Trips" value={String(s.trips ?? 0)} />
    <Kpi label="Completed" value={String(s.completed ?? 0)} accent="green" />
    <Kpi label="Cancelled" value={String(s.cancelled ?? 0)} accent="amber" />
    <Kpi label="Completion Rate" value={s.completion_rate !== null && s.completion_rate !== undefined ? `${s.completion_rate}%` : '—'} />
    <Kpi label="Avg Fare" value={money(s.average_fare ?? 0)} />
    <Kpi label="Online Time" value={durationLabel(s.online_minutes ?? 0)} />
  </section>;
  if (report.type === 'earnings') return <section className="dqx-kpis">
    <Kpi label="Trips" value={String(s.trips ?? 0)} />
    <Kpi label="Gross Earnings" value={money(s.gross_earnings ?? 0)} accent="green" />
    <Kpi label="Average Fare" value={money(s.average_fare ?? 0)} />
    <Kpi label="Distance" value={`${(s.distance_km ?? 0).toFixed(1)} km`} />
  </section>;
  if (report.type === 'shifts') return <section className="dqx-kpis">
    <Kpi label="Shifts" value={String(s.shifts ?? 0)} />
    <Kpi label="Online Time" value={durationLabel(s.online_minutes ?? 0)} />
  </section>;
  return <section className="dqx-kpis">
    <Kpi label="Trips" value={String(s.trips ?? 0)} />
    <Kpi label="Distance" value={`${(s.distance_km ?? 0).toFixed(1)} km`} />
    <Kpi label="Maintenance Events" value={String(s.maintenance_events ?? 0)} />
    {s.vehicle && <Kpi label="Vehicle" value={`${s.vehicle.make_model} · ${s.vehicle.plate_number}`} />}
  </section>;
}

function ReportTable({ report }: { report: Report }) {
  if (report.rows.length === 0) return <div className="dqx-empty"><p>No records in this period.</p></div>;
  if (report.type === 'trips') return <div className="dqx-trip-list">{(report.rows as TripReportRow[]).map(r => <article key={r.id} className="dqx-trip-row dqx-report-row">
    <div className="dqx-trip-row__field"><small>Trip</small><b>{r.trip_code}</b></div>
    <div className="dqx-trip-row__field"><small>Requested</small><b>{dateTimeOf(r.requested_at)}</b></div>
    <div className="dqx-trip-row__field dqx-trip-row__route"><small>Route</small><b><MapPin />{r.route}</b></div>
    <div className="dqx-trip-row__field"><small>Status</small><StatusChip status={r.status} /></div>
    <div className="dqx-trip-row__field"><small>Fare</small><b>{money(r.fare)}</b></div>
  </article>)}</div>;
  if (report.type === 'earnings') return <div className="dqx-trip-list">{(report.rows as EarningsReportRow[]).map(r => <article key={r.id} className="dqx-trip-row dqx-report-row">
    <div className="dqx-trip-row__field"><small>Trip</small><b>{r.trip_code}</b>{r.source === 'departure' && <span className="dqx-status-badge">Departure</span>}</div>
    <div className="dqx-trip-row__field"><small>Completed</small><b>{dateTimeOf(r.completed_at)}</b></div>
    <div className="dqx-trip-row__field dqx-trip-row__route"><small>Route</small><b><MapPin />{r.route}</b></div>
    <div className="dqx-trip-row__field"><small>Payment</small><b>{r.payment_method === 'digital' ? 'Digital' : r.payment_method === 'cash' ? 'Cash' : '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Fare</small><b>{money(r.fare)}</b></div>
  </article>)}</div>;
  if (report.type === 'shifts') return <div className="dqx-trip-list">{(report.rows as ShiftReportRow[]).map(r => <article key={r.id} className="dqx-trip-row dqx-report-row">
    <div className="dqx-trip-row__field"><small>Started</small><b>{dateTimeOf(r.started_at)}</b></div>
    <div className="dqx-trip-row__field"><small>Ended</small><b>{r.ended_at ? dateTimeOf(r.ended_at) : '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Duration</small><b>{r.duration_minutes !== null ? durationLabel(r.duration_minutes) : '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Trips</small><b>{r.trips_count}</b></div>
    <div className="dqx-trip-row__field"><small>Earnings</small><b>{money(r.earnings)}</b></div>
  </article>)}</div>;
  return <div className="dqx-trip-list">{(report.rows as VehicleReportRow[]).map(r => <article key={r.id} className="dqx-trip-row dqx-report-row">
    <div className="dqx-trip-row__field"><small>Service</small><b>{r.service_type}</b></div>
    <div className="dqx-trip-row__field"><small>Date</small><b>{dateOnly(r.serviced_at)}</b></div>
    <div className="dqx-trip-row__field"><small>Mileage</small><b>{r.mileage_km !== null ? `${r.mileage_km.toLocaleString()} km` : '—'}</b></div>
    <div className="dqx-trip-row__field"><small>Notes</small><b>{r.notes || '—'}</b></div>
  </article>)}</div>;
}

function ReportsWorkspace() {
  const [type, setType] = useState('trips');
  const [range, setRange] = useState(() => presetRange('month'));
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState('');

  const load = () => apiGet<{ report: Report }>('reports/report.php', { type, start: range.start, end: range.end }).then(response => { setReport(response.report); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load this report.'));
  useEffect(() => { void load(); }, [type, range.start, range.end]);

  return <div className="dqx-reports">
    <header className="dqx-heading">
      <div><p className="dqx-eyebrow">QUICK TAXI · REPORTS</p><h1>Reports</h1><span>Review your Quick Taxi activity and export records.</span></div>
      <div className="dqx-heading__actions"><button className="dqx-btn dqx-btn--ghost" disabled={!report} onClick={() => report && exportReportCsv(report)}><Download /> Export CSV</button></div>
    </header>

    <nav className="dqx-tabstrip">{REPORT_TYPES.map(item => <button key={item.id} className={type === item.id ? 'is-active' : ''} onClick={() => setType(item.id)}>{item.label}</button>)}</nav>

    <div className="dqx-toolbar">
      {REPORT_PRESETS.map(preset => <button key={preset.id} className="dqx-btn dqx-btn--ghost dqx-btn--sm" onClick={() => setRange(presetRange(preset.id))}>{preset.label}</button>)}
      <input type="date" value={range.start} onChange={event => setRange(current => ({ ...current, start: event.target.value }))} />
      <span className="dqx-muted">to</span>
      <input type="date" value={range.end} onChange={event => setRange(current => ({ ...current, end: event.target.value }))} />
    </div>

    {error && <p className="dqx-error">{error}</p>}
    {!report && !error && <div className="dqx-loading"><RefreshCw className="dqx-spin" /><p>Loading report…</p></div>}

    {report && <>
      <p className="dqx-muted" style={{ marginBottom: '10px' }}>Report period: {dateOnly(report.range.start)} — {dateOnly(report.range.end)}</p>
      <ReportSummaryCards report={report} />
      <h4 className="dqx-section-title">{REPORT_TYPES.find(item => item.id === report.type)?.label} details</h4>
      <ReportTable report={report} />
    </>}
  </div>;
}

function SettingsWorkspace({ boot }: { boot: Boot }) {
  const [overview, setOverview] = useState<DriverSettingsOverview | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [notificationSound, setNotificationSound] = useState(true);
  const [contactName, setContactName] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [showDeactivate, setShowDeactivate] = useState(false);
  const [deactivateReason, setDeactivateReason] = useState('');

  const load = () => apiGet<{ overview: DriverSettingsOverview }>('driver-settings/overview.php').then(response => {
    setOverview(response.overview); setError('');
    setNotificationSound(response.overview.preferences.notification_sound);
    setContactName(response.overview.preferences.emergency_contact_name || '');
    setContactPhone(response.overview.preferences.emergency_contact_phone || '');
  }).catch(reason => setError(reason instanceof Error ? reason.message : 'Uthenga could not load your settings.'));
  useEffect(() => { void load(); }, []);

  const savePreferences = async () => {
    setBusy(true); setNotice(''); setError('');
    try {
      await apiPost('driver-settings/action.php', { action: 'save_preferences', notification_sound: notificationSound, emergency_contact_name: contactName || undefined, emergency_contact_phone: contactPhone || undefined }, boot.csrf_token);
      await load(); setNotice('Settings saved.');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not save your settings.'); }
    finally { setBusy(false); }
  };

  const submitDeactivation = async () => {
    setBusy(true); setError('');
    try { await apiPost('driver-settings/action.php', { action: 'request_deactivation', reason: deactivateReason || undefined }, boot.csrf_token); await load(); setShowDeactivate(false); setDeactivateReason(''); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not submit that request.'); }
    finally { setBusy(false); }
  };

  const cancelDeactivation = async () => {
    setBusy(true); setError('');
    try { await apiPost('driver-settings/action.php', { action: 'cancel_deactivation' }, boot.csrf_token); await load(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not cancel that request.'); }
    finally { setBusy(false); }
  };

  if (!overview) return <div className="dqx-loading"><RefreshCw className="dqx-spin" /><p>Loading your settings…</p></div>;
  const { profile, deactivation } = overview;
  const verified = profile.has_driver_profile && profile.is_verified;

  return <div className="dqx-settings">
    <header className="dqx-heading"><div><p className="dqx-eyebrow">QUICK TAXI · SETTINGS</p><h1>Settings</h1><span>Manage your driver profile, preferences and account.</span></div></header>

    {error && <p className="dqx-error">{error}</p>}
    {notice && <p className="dqx-muted">{notice}</p>}

    <section className="dqx-grid">
      <article className="dqx-card">
        <header><Users /><h3>Profile</h3></header>
        <dl className="dqx-detail-list">
          <div><dt>Name</dt><dd>{profile.name || '—'}</dd></div>
          <div><dt>Email</dt><dd>{profile.email || '—'}</dd></div>
          <div><dt>Phone</dt><dd>{profile.phone || '—'}</dd></div>
          {profile.driver_code && <div><dt>Driver Code</dt><dd>{profile.driver_code}</dd></div>}
          {!!profile.rating_count && <div><dt>Rating</dt><dd>{(profile.rating_average ?? 0).toFixed(1)} ★ ({profile.rating_count})</dd></div>}
        </dl>
        <div className="dqx-drawer__actions"><a className="dqx-btn dqx-btn--ghost" href={`${driverBaseUrl()}profile.php`}>Edit Profile</a></div>
      </article>

      <article className={`dqx-card dqx-readiness ${verified ? 'is-ok' : 'is-warn'}`}>
        <header>{verified ? <ShieldCheck /> : <AlertTriangle />}<h3>Driver Verification</h3></header>
        <p>{!profile.has_driver_profile ? 'No driver verification record is on file yet.' : profile.is_verified ? 'Your driver profile is verified.' : 'Your driver profile has not been verified yet.'}</p>
        {profile.license_number && <p className="dqx-muted">License {profile.license_number}</p>}
      </article>
    </section>

    <article className="dqx-card">
      <header><Route /><h3>Quick Travel Service</h3></header>
      <p className="dqx-muted">Configure the transport service your live Quick Travel coordination requests run against — vehicle type, fare, route and departure time.</p>
      <div className="dqx-drawer__actions">
        <Link className="dqx-btn dqx-btn--ghost" to="/driver/settings">Service Settings</Link>
        <Link className="dqx-btn dqx-btn--ghost" to="/driver/lifecycle">Publish &amp; Lifecycle</Link>
      </div>
    </article>

    <article className="dqx-card">
      <header><Bell /><h3>Notifications</h3></header>
      <label className="dqx-check"><input type="checkbox" checked={notificationSound} onChange={event => setNotificationSound(event.target.checked)} /> Play a sound for new notifications</label>
    </article>

    <article className="dqx-card">
      <header><ShieldAlert /><h3>Emergency Contact</h3></header>
      <p className="dqx-muted">Stored only for your own use — Uthenga does not automatically dispatch emergency services.</p>
      <div className="dqx-vehicle-form" style={{ maxWidth: '380px', marginTop: '10px' }}>
        <label>Contact name<input value={contactName} onChange={event => setContactName(event.target.value)} placeholder="Optional" /></label>
        <label>Contact phone<input value={contactPhone} onChange={event => setContactPhone(event.target.value)} placeholder="Optional" /></label>
      </div>
      {overview.preferences.emergency_contact_phone && <PhoneContactActions phone={overview.preferences.emergency_contact_phone} label="Emergency Contact" style={{ marginTop: '10px' }} />}
    </article>

    <div className="dqx-vehicle-form__actions" style={{ marginBottom: '16px' }}>
      <button className="dqx-btn dqx-btn--primary" disabled={busy} onClick={() => void savePreferences()}>{busy ? 'Saving…' : 'Save Settings'}</button>
    </div>

    <article className="dqx-card">
      <header><SettingsIcon /><h3>Account</h3></header>
      <a className="dqx-btn dqx-btn--ghost" href={`${driverBaseUrl()}logout.php`}>Sign Out</a>
    </article>

    <article className="dqx-card dqx-issue-card">
      <header><AlertTriangle /><h3>Danger Zone</h3></header>
      {deactivation ? <>
        <p>Deactivation requested {dateTimeOf(deactivation.requested_at)}{deactivation.reason ? ` — ${deactivation.reason}` : ''}.</p>
        <button className="dqx-btn dqx-btn--ghost" disabled={busy} onClick={() => void cancelDeactivation()}>Cancel Request</button>
      </> : !showDeactivate ? <button className="dqx-btn dqx-btn--danger-ghost" onClick={() => setShowDeactivate(true)}>Request Account Deactivation</button> : <div className="dqx-vehicle-form" style={{ maxWidth: '380px' }}>
        <label>Reason<textarea rows={2} value={deactivateReason} onChange={event => setDeactivateReason(event.target.value)} placeholder="Optional" /></label>
        <div className="dqx-vehicle-form__actions"><button className="dqx-btn dqx-btn--danger-ghost" disabled={busy} onClick={() => void submitDeactivation()}>Submit Request</button><button className="dqx-btn dqx-btn--ghost" onClick={() => setShowDeactivate(false)}>Cancel</button></div>
      </div>}
    </article>
  </div>;
}
