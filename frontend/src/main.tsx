import { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter, Link, Route, Routes, useNavigate, useParams } from 'react-router-dom';
import { Activity, AlertTriangle, Bell, Bot, BriefcaseBusiness, BusFront, CalendarDays, Check, CircleX, CloudRain, FileText, Footprints, Headphones, Hotel, Layers, LayoutDashboard, LockKeyhole, MapPin, MessageSquare, Mic, MoreHorizontal, MoreVertical, Navigation, Paperclip, Phone, PhoneIncoming, PhoneOff, PhoneOutgoing, Plane, Send, Settings, Share2, Sparkles, Star, Ticket, UserPlus, Users } from 'lucide-react';
import './styles.css';
import './vendor-workspace.css';
import './location-fallback.css';
import './customer-reference.css';
import './customer-reference-overrides.css';
import './coordination-live.css';
import './trip-planner.css';
import './planning-map.css';
import { AdminControlCenter } from './admin-control-center';
import { EnterpriseAccommodationDashboard } from './accommodation-enterprise';
import { DriverQuickTaxi } from './driver-quick-taxi';

type FeatureFlags = Record<string, boolean>;
type Boot = {
  success: boolean;
  authenticated: boolean;
  user?: { id: string; name: string; role: string };
  csrf_token?: string;
  features: FeatureFlags;
  legacy_fallbacks: Record<string, string>;
  maps: { provider: string; enabled: boolean; browser_key: string };
};
type Coordinates = { latitude: number; longitude: number; accuracy_m: number | null; permission: 'GRANTED'; source: 'browser_geolocation' | 'manual_location' };
type MapPoint = { latitude: number; longitude: number };
type Route = { provider: string; distance_m: number; duration_seconds: number; geometry: { type: 'LineString'; coordinates: [number, number][] } };
type Run = { id: string; origin: string; destination: string; planned_departure_at: string; remaining_seats: number; capacity: number; fare?: number; title?: string; image?: string | null; loading_location?: string; status?: string; pickup_coordinates?: MapPoint | null };
type QueueSession = { id: string; passenger_count: number; status: string; expires_at?: string; run?: Run };
type CallRequest = { id: string; session_id: string; expires_at: string };
type Queue = { active_run: Run | null; sessions: QueueSession[]; call_requests?: CallRequest[] };
type CallState = { state: 'NONE' | 'RINGING_OUT' | 'RINGING_IN' | 'ACCEPTED' | 'DECLINED' | 'CANCELLED' | 'ENDED'; call_request_id: string | null; peer_name: string | null };
type CallSignal = { id: number; sender_role: string; kind: 'offer' | 'answer' | 'ice' | 'hangup'; payload: any; created_at: string };
type CoordinationSession = {
  session: QueueSession;
  workspace: { state: string; message: string; allowed_actions: string[]; subscriptions?: { interval_seconds: number }[] };
  viewer_role: 'customer' | 'vendor';
  messages: { id: string; sender_role: string; body: string; created_at: string }[];
  call: CallState;
  latest_locations: { actor: string; latitude: number; longitude: number; accuracy_m: number | null; source: string; captured_at: string }[];
};
type DriverSessionOption = { profile_id: string; service_id: string; seat_class_id: number; name: string; active: boolean; capacity: number; inventory_remaining: number; fare: number; origin: string; destination: string; loading_location: string; departure_time: string };

type GoogleMaps = any;

declare global {
  interface Window {
    google?: { maps?: GoogleMaps };
    __uthengaGoogleMapsLoader?: Promise<GoogleMaps>;
    __uthengaGoogleMapsReady?: () => void;
  }
}

const runtimeBase = String((window as any).UTHENGA_BASE_URL || '/').replace(/\/?$/, '/');
const apiRoot = import.meta.env.VITE_API_ROOT || `${runtimeBase}api/tie/`;

function UthengaLogoImg({ size = 'sm', className }: { size?: 'sm' | 'md' | 'lg'; className?: string }) {
  const baseUrl = (window as any).UTHENGA_BASE_URL || '/uthenga/';
  const cleanBase = baseUrl.replace(/\/$/, '');
  const darkSrc = `${cleanBase}/assets/images/logo-dark.png`;
  const lightSrc = `${cleanBase}/assets/images/logo-light.png`;
  const heights = { sm: 30, md: 38, lg: 48 };
  const h = heights[size] || 32;

  return (
    <span className={`logo-partial ${className || ''}`} style={{ display: 'inline-flex', alignItems: 'center' }}>
      <span className="logo-visual" style={{ display: 'inline-flex', alignItems: 'center', lineHeight: 0 }}>
        <img src={darkSrc} alt="Uthenga logo" className="logo-img logo-dark" style={{ height: `${h}px`, width: 'auto', objectFit: 'contain' }} />
        <img src={lightSrc} alt="Uthenga logo" className="logo-img logo-light" style={{ height: `${h}px`, width: 'auto', objectFit: 'contain' }} />
      </span>
    </span>
  );
}
async function api<T>(path: string, method = 'GET', body?: unknown, csrf?: string): Promise<T> {
  const response = await fetch(apiRoot + path, {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) throw new Error(data?.error?.message || 'Uthenga could not complete that request.');
  return data as T;
}

// Real distance between two points — used for honest walking-progress
// percentages when no routed distance is available, and for stop
// detection (comparing consecutive real GPS fixes), never a fabricated figure.
function haversineMeters(a: { latitude: number; longitude: number }, b: { latitude: number; longitude: number }): number {
  const R = 6371000;
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = toRad(b.latitude - a.latitude);
  const dLng = toRad(b.longitude - a.longitude);
  const sinLat = Math.sin(dLat / 2);
  const sinLng = Math.sin(dLng / 2);
  const h = sinLat * sinLat + Math.cos(toRad(a.latitude)) * Math.cos(toRad(b.latitude)) * sinLng * sinLng;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

function foregroundLocation(): Promise<Coordinates> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('This device cannot share its location. Use a supported browser to coordinate a live departure.'));
    navigator.geolocation.getCurrentPosition(
      position => {
        const accuracy = position.coords.accuracy;
        if (!Number.isFinite(accuracy) || accuracy > 100) {
          reject(new Error(`Location accuracy is too low (about ${Math.round(accuracy || 0)} m). Uthenga will not place a live marker from a coarse network position. Use a GPS-capable phone, or pin your exact point on the map instead.`));
          return;
        }
        resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: accuracy, permission: 'GRANTED', source: 'browser_geolocation' });
      },
      () => reject(new Error('Location sharing is required to coordinate a live departure. Enable it in your browser and try again.')),
      // Always request a fresh high-accuracy foreground reading. We never use
      // an IP-derived or previously cached location for live coordination.
      { enableHighAccuracy: true, timeout: 20_000, maximumAge: 0 },
    );
  });
}

// Interaction feedback is deliberately user-initiated: vibration and the
// subtle confirmation tone never run on page load or while a user is idle.
function intelligentFeedback(kind: 'confirm' | 'attention' = 'confirm') {
  try { navigator.vibrate?.(kind === 'confirm' ? 12 : [18, 24, 18]); } catch { /* unsupported device */ }
  try {
    const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioContext) return;
    const context = new AudioContext(); const oscillator = context.createOscillator(); const gain = context.createGain();
    oscillator.frequency.value = kind === 'confirm' ? 660 : 390; gain.gain.setValueAtTime(.018, context.currentTime); gain.gain.exponentialRampToValueAtTime(.0001, context.currentTime + .07);
    oscillator.connect(gain).connect(context.destination); oscillator.start(); oscillator.stop(context.currentTime + .075);
  } catch { /* browsers may require an explicit gesture; this helper is only called from one */ }
}

function App() {
  const [boot, setBoot] = useState<Boot | null>(null);
  const [bootError, setBootError] = useState('');
  useEffect(() => { api<Boot>('frontend/bootstrap.php').then(setBoot).catch(error => setBootError(error.message)); }, []);
  if (bootError) return <SystemState title="Uthenga is unavailable" message={bootError} />;
  if (!boot) return <SystemState title="Connecting to Uthenga" message="Restoring your secure workspace…" busy />;
  if (!boot.authenticated) return <SystemState title="Sign in required" message="Your Uthenga session has ended. Sign in to continue." href={boot.legacy_fallbacks?.login} />;
  return <>
    <Routes>
      <Route path="/vendor" element={<VendorWorkspace boot={boot} />} />
      <Route path="/driver" element={<DriverQuickTaxi boot={boot} />} />
      <Route path="/driver/settings" element={<DriverSettings boot={boot} />} />
      <Route path="/driver/session" element={<DriverSessionComposer boot={boot} />} />
      <Route path="/driver/lifecycle" element={<DriverLifecycleWorkspace boot={boot} />} />
      <Route path="/assistant" element={<AssistantWorkspace boot={boot} />} />
      <Route path="/planner" element={<TripPlanningWorkspace boot={boot} />} />
      <Route path="/accommodation" element={<EnterpriseAccommodationDashboard boot={boot} />} />
      <Route path="/admin" element={<AdminControlCenter boot={boot} />} />
      <Route path="/admin/service-reviews" element={<ServiceReviewDesk boot={boot} />} />
      <Route path="/service/:kind" element={<ServiceWorkspaceShell boot={boot} />} />
      <Route path="*" element={<CustomerDashboardV3 boot={boot} />} />
    </Routes>
    <AssistantDock boot={boot} />
  </>;
}

function SystemState({ title, message, href, busy }: { title: string; message: string; href?: string; busy?: boolean }) {
  return <main className="system-state"><div className={busy ? 'orb orb--active' : 'orb'} aria-hidden="true" /><p className="eyebrow">UTHENGA INTELLIGENCE</p><h1>{title}</h1><p>{message}</p>{href && <a className="button button--primary" href={href}>Open secure sign in</a>}</main>;
}

function AssistantDock({ boot }: { boot: Boot }) {
  if (['Administrator', 'Super Administrator'].includes(boot.user?.role || '')) return null;
  if (boot.user?.role === 'vendor') return <div className="workspace-dock"><Link className="assistant-dock" to="/assistant"><span>✦</span><b>Ask Uthenga</b><small>Verified travel guidance</small></Link><Link className="assistant-dock assistant-dock--driver" to="/driver/lifecycle"><span>◇</span><b>Service release</b><small>Review & publication</small></Link><Link className="assistant-dock assistant-dock--driver" to="/driver/session"><span>◉</span><b>Open departure</b><small>Driver Operations</small></Link></div>;
  return <Link className="assistant-dock" to="/assistant"><span>✦</span><b>Ask Uthenga</b><small>Verified travel guidance</small></Link>;
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

function CustomerMap({ location, peerLocation, route, pickup, mapsConfig, manualSelectionEnabled = false, onManualLocation, onLocate }: { location: Coordinates | null; peerLocation?: MapPoint | null; route?: Route | null; pickup?: MapPoint | null; mapsConfig: Boot['maps']; manualSelectionEnabled?: boolean; onManualLocation?: (location: Coordinates) => void; onLocate?: () => void | Promise<void> }) {
  const container = useRef<HTMLDivElement | null>(null);
  const map = useRef<any>(null);
  const maps = useRef<GoogleMaps | null>(null);
  const marker = useRef<any>(null);
  const pickupMarker = useRef<any>(null);
  const peerMarker = useRef<any>(null);
  const routeLine = useRef<any>(null);
  const clickListener = useRef<any>(null);
  const locateControl = useRef<HTMLButtonElement | null>(null);
  const [mapLoadError, setMapLoadError] = useState('');
  const [locationNotice, setLocationNotice] = useState('');
  const latestLocation = useRef<Coordinates | null>(location);
  const manualSelection = useRef(manualSelectionEnabled);
  const automaticPin = useRef(false);
  const manualLocationCallback = useRef(onManualLocation);
  latestLocation.current = location;
  manualSelection.current = manualSelectionEnabled;
  manualLocationCallback.current = onManualLocation;
  const locateCallback = useRef(onLocate);
  locateCallback.current = onLocate;
  useEffect(() => {
    if (!container.current || map.current || !mapsConfig.enabled || !mapsConfig.browser_key) return;
    let disposed = false;
    setMapLoadError('');
    void loadGoogleMaps(mapsConfig.browser_key).then(googleMaps => {
      if (disposed || !container.current) return;
      maps.current = googleMaps;
      const activeLocation = latestLocation.current;
      const center = activeLocation ? { lat: activeLocation.latitude, lng: activeLocation.longitude } : { lat: -13.9626, lng: 33.7741 };
      const instance = new googleMaps.Map(container.current, { center, zoom: activeLocation ? 13 : 10, styles: googleDarkMapStyle, disableDefaultUI: true, zoomControl: true, zoomControlOptions: { position: googleMaps.ControlPosition.RIGHT_BOTTOM }, mapTypeControl: true, mapTypeControlOptions: { position: googleMaps.ControlPosition.TOP_RIGHT, style: googleMaps.MapTypeControlStyle.HORIZONTAL_BAR }, gestureHandling: 'greedy', clickableIcons: false, backgroundColor: '#07182a' });
      map.current = instance;
      clickListener.current = instance.addListener('click', (event: any) => {
        if ((!manualSelection.current && !automaticPin.current) || !manualLocationCallback.current || !event.latLng) return;
        automaticPin.current = false; setLocationNotice('');
        manualLocationCallback.current({ latitude: event.latLng.lat(), longitude: event.latLng.lng(), accuracy_m: null, permission: 'GRANTED', source: 'manual_location' });
      });
      if (locateCallback.current || manualLocationCallback.current) {
        const control = document.createElement('button');
        control.type = 'button'; control.className = 'uthenga-map-locate-control';
        control.title = 'Use my current location'; control.setAttribute('aria-label', 'Use my current location'); control.textContent = '⌖';
        control.addEventListener('click', async event => {
          event.preventDefault(); event.stopPropagation();
          const locate = locateCallback.current || (() => foregroundLocation().then(nextLocation => manualLocationCallback.current?.(nextLocation)));
          if (control.disabled) return;
          setLocationNotice(''); control.disabled = true; control.classList.add('is-locating');
          try { await locate(); }
          catch {
            automaticPin.current = Boolean(manualLocationCallback.current);
            setLocationNotice('This device can only provide a coarse network estimate. Click your exact point on the map to use it for this trip, or use a GPS-capable phone.');
          }
          finally { control.disabled = false; control.classList.remove('is-locating'); }
        });
        instance.controls[googleMaps.ControlPosition.RIGHT_BOTTOM].push(control); locateControl.current = control;
      }
    }).catch((error: unknown) => { if (!disposed) setMapLoadError(error instanceof Error ? error.message : 'Google Maps could not be loaded.'); });
    return () => { disposed = true; clickListener.current?.remove(); locateControl.current?.remove(); marker.current?.setMap(null); pickupMarker.current?.setMap(null); peerMarker.current?.setMap(null); routeLine.current?.setMap(null); map.current = null; maps.current = null; };
  }, [mapsConfig.browser_key, mapsConfig.enabled]);
  useEffect(() => {
    if (!map.current || !maps.current || !location) return;
    const point = { lat: location.latitude, lng: location.longitude };
    marker.current?.setMap(null); marker.current = new maps.current.Marker({ position: point, map: map.current, title: location.source === 'manual_location' ? 'Pinned trip location' : 'Your current location', icon: { path: maps.current.SymbolPath.CIRCLE, scale: 9, fillColor: '#31e99d', fillOpacity: 1, strokeColor: '#e6ffff', strokeWeight: 3 } });
    map.current.panTo(point); map.current.setZoom(13);
  }, [location]);
  useEffect(() => {
    if (!map.current || !maps.current) return;
    peerMarker.current?.setMap(null); peerMarker.current = null;
    if (!peerLocation) return;
    peerMarker.current = new maps.current.Marker({ position: { lat: peerLocation.latitude, lng: peerLocation.longitude }, map: map.current, title: 'Driver live location', label: { text: '▣', color: '#062315', fontWeight: 'bold' }, icon: { path: maps.current.SymbolPath.CIRCLE, scale: 11, fillColor: '#3adf99', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 } });
  }, [peerLocation]);
  useEffect(() => {
    if (!map.current || !maps.current) return;
    pickupMarker.current?.setMap(null); routeLine.current?.setMap(null); pickupMarker.current = null; routeLine.current = null;
    if (pickup) { pickupMarker.current = new maps.current.Marker({ position: { lat: pickup.latitude, lng: pickup.longitude }, map: map.current, title: 'Trip destination', icon: { path: maps.current.SymbolPath.CIRCLE, scale: 8, fillColor: '#ff5d85', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } }); if (!location) { map.current.panTo({ lat: pickup.latitude, lng: pickup.longitude }); map.current.setZoom(11); } }
    const points = route?.geometry?.coordinates?.map(([longitude, latitude]) => ({ lat: latitude, lng: longitude })) || [];
    if (points.length > 1) {
      routeLine.current = new maps.current.Polyline({ path: points, geodesic: false, strokeColor: '#36e7ad', strokeOpacity: .92, strokeWeight: 5, map: map.current });
      const bounds = new maps.current.LatLngBounds(); points.forEach((point: { lat: number; lng: number }) => bounds.extend(point)); map.current.fitBounds(bounds, 26);
    }
  }, [location, pickup, route]);
  if (!mapsConfig.enabled || !mapsConfig.browser_key) return <div className="customer-v2__map-fallback"><b>Google Maps is not configured</b><span>Add the restricted browser key as <code>TIE_GOOGLE_MAPS_BROWSER_KEY</code> to enable the live map.</span></div>;
  return <><div className="customer-v2__mapbox" ref={container} />{mapLoadError && <div className="customer-v2__map-fallback"><b>Google Maps is unavailable</b><span>{mapLoadError}</span></div>}{locationNotice && <div className="uthenga-map-location-notice">{locationNotice}</div>}</>;
}

function CustomerDashboardV2({ boot }: { boot: Boot }) {
  const prompts = [['Where are you heading to?', 'Mzuzu, Area 25, Blantyre…'], ['When would you like to leave?', 'Today around 3 PM'], ['How many people are travelling?', '1']];
  const initialDestination = new URLSearchParams(window.location.search).get('destination') || '';
  const [step, setStep] = useState(initialDestination ? 1 : 0); const [values, setValues] = useState([initialDestination, '', '1']); const [preference, setPreference] = useState('Most Comfortable'); const [location, setLocation] = useState<Coordinates | null>(null); const [manualSelectionEnabled, setManualSelectionEnabled] = useState(false); const [runs, setRuns] = useState<Run[]>([]); const [selected, setSelected] = useState<Run | null>(null); const [route, setRoute] = useState<Route | null>(null); const [working, setWorking] = useState(false); const [error, setError] = useState('');
  const destination = values[0].trim(); const prompt = prompts[step];
  const update = (value: string) => setValues(current => current.map((item, index) => index === step ? value : item));
  const next = () => { if (!values[step]?.trim()) { setError('Please answer this question to continue.'); return; } setError(''); setStep(current => current + 1); };
  const discover = async () => { if (!destination) return; setWorking(true); setError(''); try { const current = location || await foregroundLocation(); setLocation(current); const response = await api<{ discovery: { runs: Run[] } }>('coordination/discover.php', 'POST', { destination, passenger_count: Math.max(1, Number(values[2]) || 1), location: current, preference }, boot.csrf_token); const found = response.discovery.runs || []; setRuns(found); setSelected(found[0] || null); setStep(4); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not find a live departure.'); } finally { setWorking(false); } };
  const chooseShortcut = (place: string) => { setValues(current => [place, current[1], current[2]]); setStep(1); };
  const chooseManualLocation = (nextLocation: Coordinates) => { setLocation(nextLocation); setManualSelectionEnabled(false); setError(''); };
  useEffect(() => {
    if (!boot.features.routing || !location || !selected?.pickup_coordinates) { setRoute(null); return; }
    let cancelled = false;
    api<{ route: Route }>('routing/route.php', 'POST', { origin: location, destination: selected.pickup_coordinates }, boot.csrf_token)
      .then(result => { if (!cancelled) setRoute(result.route); })
      .catch(() => { if (!cancelled) setRoute(null); });
    return () => { cancelled = true; };
  }, [boot.csrf_token, boot.features.routing, location, selected]);
  return <main className="customer-v2"><header className="customer-v2__header"><Link to="/" className="customer-v2__brand"><UthengaLogoImg size="sm" /><b><small style={{ display: 'block', fontSize: '0.65rem', color: '#4ee7ac', marginTop: '2px', fontWeight: 600 }}>Quick Trip Planner</small></b></Link><div className="customer-v2__account"><a href={boot.legacy_fallbacks?.customer_workspace || "/uthenga/dashboard.php"} style={{ background: '#e63946', color: '#fff', padding: '0.4rem 0.85rem', borderRadius: '8px', fontWeight: 700, textDecoration: 'none', fontSize: '0.85rem' }}>← Dashboard</a><button type="button" onClick={() => { const current = document.documentElement.dataset.theme || 'light'; const next = current === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = next; document.documentElement.className = (document.documentElement.className || '').replace(/theme-(light|dark)/, '') + ' theme-' + next; try { localStorage.setItem('uthenga-theme', next); } catch(e){} }} style={{ background: 'rgba(255,255,255,0.12)', border: '1px solid rgba(255,255,255,0.2)', color: 'inherit', padding: '0.4rem 0.75rem', borderRadius: '8px', cursor: 'pointer', fontWeight: 600, fontSize: '0.8rem' }}>🌓 Theme</button><span>🌤 &nbsp;26°C</span><i>♧</i><div>{boot.user?.name?.slice(0, 1) || 'U'}<em /></div><p>{boot.user?.name || 'Traveller'}<small>Online</small></p></div></header><div className="customer-v2__layout"><aside className="customer-v2__draft"><header><b>YOUR TRIP (DRAFT)</b><button onClick={() => { setStep(0); setValues(['', '', '1']); setRuns([]); setSelected(null); setRoute(null); setLocation(null); }}>Clear</button></header><dl><div><dt>● &nbsp;From</dt><dd>{location ? 'Current Location' : 'Location required'}<small>{location ? location.source === 'manual_location' ? 'Pinned manually for this trip' : `Device accuracy ${Math.round(location.accuracy_m || 0)} m` : 'Share or pin your current location'}</small></dd></div><div><dt>● &nbsp;To</dt><dd>{destination || 'Not set yet'}<small>{destination ? 'Requested destination' : '—'}</small></dd></div><div><dt>▦ &nbsp;Date</dt><dd>{values[1] || 'Not set yet'}</dd></div><div><dt>♧ &nbsp;Passengers</dt><dd>{values[2] || '1'} passenger{values[2] === '1' ? '' : 's'}</dd></div><div><dt>★ &nbsp;Preference</dt><dd>{step >= 4 ? preference : 'Not set yet'}</dd></div></dl></aside><section className="customer-v2__console"><div className="customer-v2__prompt"><div className="customer-v2__orb">◉</div><div><h1>Hi {boot.user?.name?.split(' ')[0] || 'Traveller'}! <span>👋</span></h1><p>I’m your travel assistant.<br/>Let’s get you there safely.</p></div></div>{step < 3 && prompt && <div className="customer-v2__question"><h2>{prompt[0]}</h2><input autoFocus value={values[step]} onChange={event => update(event.target.value)} onKeyDown={event => event.key === 'Enter' && next()} /><div className="customer-v2__shortcuts"><small>SUGGESTED SHORTCUTS</small><button onClick={() => chooseShortcut('Mzuzu')}>▣ &nbsp; Go to Mzuzu</button><button onClick={() => chooseShortcut('Lilongwe Airport')}>✈ &nbsp; Airport Transfer</button><button onClick={() => chooseShortcut('Lilongwe City Centre')}>▣ &nbsp; Work Commute</button><button onClick={() => setStep(0)}>••• &nbsp; More</button></div><button className="customer-v2__continue" onClick={next}>Continue →</button></div>}{step === 3 && <div className="customer-v2__question customer-v2__preferences"><h2>How should Uthenga rank the live options?</h2><div>{['Cheapest', 'Fastest', 'Most Comfortable'].map(option => <button className={preference === option ? 'is-selected' : ''} key={option} onClick={() => setPreference(option)}>{option}</button>)}</div><button className="customer-v2__continue" onClick={discover} disabled={working}>{working ? 'Sharing location & finding transport…' : 'Find live transport →'}</button></div>}{step === 4 && <div className="customer-v2__results"><header><b>↟ &nbsp;{runs.length} MATCHING TRANSPORT{runs.length === 1 ? '' : 'S'} FOUND</b><span>Sorted by: <strong>{preference}</strong></span><button onClick={() => setStep(0)}>×</button></header>{runs.length ? runs.slice(0, 3).map((run, index) => <article className={selected?.id === run.id ? 'is-selected' : ''} key={run.id} onClick={() => setSelected(run)}><div className="customer-v2__vehicle">▣<small>{index === 0 ? 'RECOMMENDED' : 'LIVE'}</small></div><div><h3>{run.title || 'Uthenga Transport'}</h3><p>{run.loading_location || run.origin}</p><small>◷ Departs {new Date(run.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} &nbsp; · &nbsp; Seats left: {run.remaining_seats}</small></div><aside><b>{run.fare ? `MWK ${Number(run.fare).toLocaleString()}` : 'Fare pending'}</b><small>Per seat</small><em>{index === 0 ? 'Best Match' : preference}</em></aside></article>) : <p className="customer-v2__empty">No live Uthenga departure matches this request yet. Adjust the destination or check shortly.</p>}</div>}{error && <p className="inline-error">{error}</p>}<div className="customer-v2__answer"><span>▣</span><input value={step < 3 ? values[step] : ''} onChange={event => step < 3 && update(event.target.value)} onKeyDown={event => event.key === 'Enter' && step < 3 && next()} placeholder="Type your answer…"/><button onClick={() => step < 3 ? next() : step === 3 ? discover() : setStep(0)}>➤</button></div></section><aside className="customer-v2__right"><article className={'customer-v2__map' + (manualSelectionEnabled ? ' is-selecting-location' : '')}><CustomerMap location={location} route={route} pickup={selected?.pickup_coordinates} mapsConfig={boot.maps} manualSelectionEnabled={manualSelectionEnabled} onManualLocation={chooseManualLocation}/>{selected && <div className="customer-v2__map-label"><b>{selected.loading_location || selected.origin}</b><span>{route ? `${Math.round(route.distance_m / 1000 * 10) / 10} km road route · ${Math.ceil(route.duration_seconds / 60)} min` : 'Verified boarding pickup'}</span></div>}{manualSelectionEnabled && <div className="manual-location-instruction">Click your exact location on the map to use it for this trip.</div>}</article>{selected ? <article className="customer-v2__departure"><header><b>DEPARTURE SELECTED</b><em>● LIVE</em></header><div><span>▣</span><p><b>{selected.title || 'Uthenga Transport'}</b><small>Departs from {selected.loading_location || selected.origin}</small><small>Seats available: {selected.remaining_seats}</small></p><strong>{new Date(selected.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</strong></div><button onClick={() => void requestSeat(selected, boot, location, values, setError)}>Request boarding</button></article> : <article className="customer-v2__arrival"><b>YOUR TRIP LOCATION</b><p>{location ? location.source === 'manual_location' ? 'Pinned manually for this trip. It is not saved as location history.' : `Device location shared · accuracy ${Math.round(location.accuracy_m || 0)} m` : 'Share a precise device location, or explicitly pin your current point on the map.'}</p><div className="location-fallback-actions"><button onClick={() => void foregroundLocation().then(nextLocation => { setLocation(nextLocation); setError(''); }).catch(reason => setError(reason instanceof Error ? reason.message : 'Unable to refresh device location.'))}>Refresh device location</button><button onClick={() => setManualSelectionEnabled(active => !active)}>{manualSelectionEnabled ? 'Cancel map pin' : 'Pin my location on map'}</button></div></article>}<article className="customer-v2__advice"><b>✦ &nbsp;AI ADVICE</b><p>{selected ? 'Review the selected departure, then request boarding. The driver must accept before your seat is held.' : 'Choose a destination and Uthenga will use your foreground location only for live coordination.'}</p></article></aside></div><footer className="customer-v2__actions"><button disabled={!selected}>☎ &nbsp; Call Driver</button><button disabled={!selected}>⊗ &nbsp; Cancel Trip</button><button>⌯ &nbsp; Share Trip</button><Link to="/assistant">◉ &nbsp; Need Help?</Link></footer></main>;
}

// A real countdown to the departure this customer actually selected — ticks
// client-side against the server-issued planned_departure_at, never a guess.
function DepartureCountdown({ departureAt }: { departureAt: string }) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => { const interval = window.setInterval(() => setNow(Date.now()), 1_000); return () => window.clearInterval(interval); }, []);
  const diffMs = new Date(departureAt).getTime() - now;
  if (diffMs <= 0) return <small className="quick-reference__countdown is-departing">Departing now</small>;
  const totalSeconds = Math.floor(diffMs / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return <small className="quick-reference__countdown">{minutes}:{String(seconds).padStart(2, '0')} until departure</small>;
}

function CustomerDashboardV3({ boot }: { boot: Boot }) {
  const prompts = [['Where are you heading to?', 'Mzuzu, Area 25, Blantyre…'], ['When would you like to leave?', 'Today around 3 PM'], ['How many people are travelling?', '1']];
  const initialDestination = new URLSearchParams(window.location.search).get('destination') || '';
  const [step, setStep] = useState(initialDestination ? 1 : 0);
  const [values, setValues] = useState([initialDestination, '', '1']);
  const [preference, setPreference] = useState('Most Comfortable');
  const [location, setLocation] = useState<Coordinates | null>(null);
  const [manualSelectionEnabled, setManualSelectionEnabled] = useState(false);
  const [runs, setRuns] = useState<Run[]>([]);
  const [selected, setSelected] = useState<Run | null>(null);
  const [route, setRoute] = useState<Route | null>(null);
  const [working, setWorking] = useState(false);
  const [error, setError] = useState('');
  const [sessionId, setSessionId] = useState(() => sessionStorage.getItem('uthenga.coordination.customer_session') || '');
  const [liveCoordination, setLiveCoordination] = useState<CoordinationSession | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [showInbox, setShowInbox] = useState(false);
  const [directUnread, setDirectUnread] = useState(0);
  useEffect(() => {
    const load = () => api<{ result: { threads: DirectThreadSummary[] } }>('direct-message/inbox.php')
      .then(response => setDirectUnread(response.result.threads.reduce((total, t) => total + t.unread_count, 0)))
      .catch(() => undefined);
    void load(); const interval = window.setInterval(load, 15_000); return () => window.clearInterval(interval);
  }, []);
  const destination = values[0].trim();
  const prompt = prompts[step];
  const update = (value: string) => setValues(current => current.map((item, index) => index === step ? value : item));
  const next = () => {
    if (!values[step]?.trim()) { setError('Please answer this question to continue.'); return; }
    intelligentFeedback(); setError(''); setStep(current => current + 1);
  };
  const discover = async () => {
    if (!destination) return;
    setWorking(true); setError('');
    try {
      const current = location || await foregroundLocation();
      setLocation(current);
      const response = await api<{ discovery: { runs: Run[] } }>('coordination/discover.php', 'POST', { destination, passenger_count: Math.max(1, Number(values[2]) || 1), location: current, preference }, boot.csrf_token);
      const found = response.discovery.runs || [];
      setRuns(found); setSelected(found[0] || null); intelligentFeedback('confirm'); setStep(4);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not find a live departure.'); }
    finally { setWorking(false); }
  };
  const chooseManualLocation = (nextLocation: Coordinates) => { setLocation(nextLocation); setManualSelectionEnabled(false); setError(''); };
  const reset = () => { setStep(0); setValues(['', '', '1']); setRuns([]); setSelected(null); setRoute(null); setLocation(null); setError(''); setSessionId(''); setLiveCoordination(null); sessionStorage.removeItem('uthenga.coordination.customer_session'); };
  const startBoardingRequest = async () => {
    if (!selected) return;
    const nextSessionId = await requestSeat(selected, boot, location, values, setError);
    if (nextSessionId) { setSessionId(nextSessionId); sessionStorage.setItem('uthenga.coordination.customer_session', nextSessionId); }
  };

  // Real-time walking progress toward the pickup point. Activated only once
  // the customer has pressed "I'm on my way" (CUSTOMER_EN_ROUTE) — never
  // before, and never fabricated: every figure here derives from an actual
  // GPS fix or the real route distance captured at the moment walking began.
  const [walkStartedAt, setWalkStartedAt] = useState<number | null>(null);
  const [walkStartDistanceM, setWalkStartDistanceM] = useState<number | null>(null);
  const [walkAdvisory, setWalkAdvisory] = useState<{ type: 'stopped' | 'delayed'; message: string } | null>(null);
  const [shareStatus, setShareStatus] = useState('');
  const walkHistoryRef = useRef<{ lat: number; lng: number; t: number }[]>([]);
  const isWalking = liveCoordination?.session.status === 'CUSTOMER_EN_ROUTE';

  useEffect(() => {
    if (!isWalking) { setWalkStartedAt(null); setWalkStartDistanceM(null); setWalkAdvisory(null); walkHistoryRef.current = []; return; }
    if (walkStartedAt === null) { setWalkStartedAt(Date.now()); setWalkStartDistanceM(route?.distance_m ?? null); walkHistoryRef.current = []; }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isWalking]);

  useEffect(() => {
    if (!isWalking || !sessionId || !navigator.geolocation) return;
    const watchId = navigator.geolocation.watchPosition(
      position => {
        const coords: Coordinates = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: position.coords.accuracy, permission: 'GRANTED', source: 'browser_geolocation' };
        setLocation(coords);
        walkHistoryRef.current = [...walkHistoryRef.current, { lat: coords.latitude, lng: coords.longitude, t: Date.now() }].filter(p => Date.now() - p.t < 180_000);
        void api('coordination/action.php', 'POST', { action: 'location', session_id: sessionId, location: coords }, boot.csrf_token).catch(() => undefined);
      },
      () => undefined,
      { enableHighAccuracy: true, maximumAge: 8_000, timeout: 20_000 },
    );
    return () => navigator.geolocation.clearWatch(watchId);
  }, [isWalking, sessionId, boot.csrf_token]);

  // Deterministic advisory only — never an LLM call, never an invented
  // position or ETA. Compares real consecutive GPS fixes (stopped?) and real
  // elapsed time against the real route estimate captured at walk-start (delayed?).
  useEffect(() => {
    if (!isWalking || walkStartedAt === null) { setWalkAdvisory(null); return; }
    const tick = () => {
      const now = Date.now();
      const recent = walkHistoryRef.current.filter(p => now - p.t < 120_000);
      if (recent.length >= 2 && now - recent[0].t > 90_000) {
        const moved = haversineMeters({ latitude: recent[0].lat, longitude: recent[0].lng }, { latitude: recent[recent.length - 1].lat, longitude: recent[recent.length - 1].lng });
        if (moved < 20) { setWalkAdvisory({ type: 'stopped', message: "It looks like you've stopped moving. Would you like to try a different route, or call the driver to let them know?" }); return; }
      }
      const elapsedSeconds = (now - walkStartedAt) / 1000;
      if (route?.duration_seconds && elapsedSeconds > route.duration_seconds * 1.6 && elapsedSeconds > 90) {
        setWalkAdvisory({ type: 'delayed', message: `You're taking longer than the expected ${Math.ceil(route.duration_seconds / 60)} min walk. Consider calling the driver to let them know.` });
        return;
      }
      setWalkAdvisory(null);
    };
    tick();
    const interval = window.setInterval(tick, 15_000);
    return () => window.clearInterval(interval);
  }, [isWalking, walkStartedAt, route?.duration_seconds]);

  const walkProgressPercent = walkStartDistanceM && route?.distance_m !== undefined
    ? Math.max(0, Math.min(100, Math.round((1 - (route.distance_m / walkStartDistanceM)) * 100)))
    : null;

  const callDriver = async () => {
    if (!sessionId) return;
    try { await api('coordination/action.php', 'POST', { action: 'request_call', session_id: sessionId }, boot.csrf_token); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not start the call.'); }
  };
  const cancelTrip = async () => {
    if (!sessionId) return;
    if (!window.confirm('Cancel this trip request?')) return;
    try { await api('coordination/action.php', 'POST', { action: 'customer_action', session_id: sessionId, customer_action: 'CANCEL' }, boot.csrf_token); reset(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not cancel this trip.'); }
  };
  const shareTrip = async () => {
    const text = selected ? `I'm taking a Quick Taxi from ${selected.loading_location || selected.origin}, departing ${new Date(selected.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}.` : "I'm booking a Quick Taxi on Uthenga.";
    try {
      if (navigator.share) { await navigator.share({ title: 'My Uthenga Quick Taxi trip', text }); }
      else { await navigator.clipboard.writeText(text); setShareStatus('Trip details copied to clipboard.'); window.setTimeout(() => setShareStatus(''), 4000); }
    } catch { /* user dismissed the share sheet; not an error */ }
  };
  useEffect(() => {
    if (!boot.features.routing || !location || !selected?.pickup_coordinates) { setRoute(null); return; }
    let cancelled = false;
    api<{ route: Route }>('routing/route.php', 'POST', { origin: location, destination: selected.pickup_coordinates }, boot.csrf_token)
      .then(result => { if (!cancelled) setRoute(result.route); })
      .catch(() => { if (!cancelled) setRoute(null); });
    return () => { cancelled = true; };
  }, [boot.csrf_token, boot.features.routing, location, selected]);
  const departureTime = selected ? new Date(selected.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
  const driverLocation = liveCoordination?.latest_locations.find(item => item.actor === 'vendor') || null;
  const distanceText = route ? `${Math.round(route.distance_m)} metres away` : location ? 'Pickup point selected' : 'Share your location to measure the walk';
  return <main className="quick-reference">
    <header className="quick-reference__header">
      <Link to="/" className="quick-reference__brand"><UthengaLogoImg size="sm" /><span><small style={{ display: 'block', fontSize: '0.65rem', color: '#4ee7ac', marginTop: '2px', fontWeight: 600 }}>Quick Taxi</small></span></Link>
      <div className="quick-reference__account"><a href={boot.legacy_fallbacks?.main_dashboard || "/uthenga/dashboard.php"} className="quick-reference__planner-link" style={{ background: '#e63946', color: '#fff', padding: '0.4rem 0.85rem', borderRadius: '8px', fontWeight: 700, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>← Dashboard</a><button type="button" className="quick-reference__history-link" onClick={() => setShowHistory(true)}><FileText size={15} /> Trip History</button><button type="button" className="quick-reference__history-link" onClick={() => setShowInbox(true)}><MessageSquare size={15} /> Messages{directUnread > 0 && <em>{directUnread}</em>}</button><Link className="quick-reference__planner-link" to="/planner">Plan a trip</Link><span>🌤 &nbsp;26°C</span><Bell /><div className="quick-reference__avatar">{boot.user?.name?.slice(0, 1) || 'U'}<i /></div><p>{boot.user?.name || 'Traveller'}<small>Online</small></p></div>
    </header>
    <div className="quick-reference__grid">
      <aside className="quick-reference__draft">
        <header><b>YOUR TRIP (DRAFT)</b><button onClick={reset}>Clear</button></header>
        <dl>
          <div><MapPin /><dt>From</dt><dd>{location ? 'Current Location' : 'Location required'}<small>{location ? location.source === 'manual_location' ? 'Pinned manually for this trip' : `Device accuracy ${Math.round(location.accuracy_m || 0)} m` : 'Use the map control or pin your location'}</small></dd><button className="draft-locate" title="Pin or refresh location" onClick={() => setManualSelectionEnabled(true)}>⌖</button></div>
          <div><MapPin /><dt>To</dt><dd>{destination || 'Not set yet'}<small>{destination ? 'Requested destination' : '—'}</small></dd></div>
          <div><CalendarDays /><dt>Date</dt><dd>{values[1] || 'Not set yet'}</dd></div>
          <div><Users /><dt>Passengers</dt><dd>{values[2] || '1'} passenger{values[2] === '1' ? '' : 's'}</dd></div>
          <div><Star /><dt>Preference</dt><dd>{step >= 3 ? preference : 'Not set yet'}</dd></div>
        </dl>
      </aside>
      <section className="quick-reference__workspace">
        <section className="quick-reference__hero">
          <div className="quick-reference__bot"><Bot /></div>
          <div className="quick-reference__greeting"><h1>Hi {boot.user?.name?.split(' ')[0] || 'Traveller'}! <span>👋</span></h1><p>I’m your travel assistant.<br/>Let’s get you there safely.</p></div>
          {step < 3 && prompt && <div className="quick-reference__question"><h2>{prompt[0]}</h2><div className="quick-reference__line-input"><input autoFocus value={values[step]} placeholder={prompt[1]} onChange={event => update(event.target.value)} onKeyDown={event => event.key === 'Enter' && next()} /></div><div className="quick-reference__shortcuts"><small>SUGGESTED SHORTCUTS</small><button onClick={() => { setValues(current => ['Mzuzu', current[1], current[2]]); setStep(1); }}><BusFront />Go to Mzuzu</button><button onClick={() => { setValues(current => ['Lilongwe Airport', current[1], current[2]]); setStep(1); }}><Plane />Airport Transfer</button><button onClick={() => { setValues(current => ['Lilongwe City Centre', current[1], current[2]]); setStep(1); }}><BriefcaseBusiness />Work Commute</button><button onClick={() => setStep(0)}><MoreHorizontal />More</button></div><div className="quick-reference__question-actions">{step > 0 && <button className="quick-reference__back" onClick={() => { setError(''); setStep(current => current - 1); }}>← Back</button>}<button className="quick-reference__continue" onClick={next}>Continue</button></div></div>}
          {step === 3 && <div className="quick-reference__question"><h2>Do you prefer</h2><div className="quick-reference__choices">{['Cheapest', 'Fastest', 'Most Comfortable'].map(option => <button className={preference === option ? 'is-selected' : ''} key={option} onClick={() => setPreference(option)}>{option}</button>)}</div><div className="quick-reference__question-actions"><button className="quick-reference__back" onClick={() => { setError(''); setStep(2); }}>← Back</button><button className="quick-reference__continue" onClick={discover} disabled={working}>{working ? 'Finding live transport…' : 'Find live transport'}</button></div></div>}
        </section>
        {step === 4 && <section className="quick-reference__results"><header><b><Navigation /> {runs.length} MATCHING TRANSPORT{runs.length === 1 ? '' : 'S'} FOUND</b><span>Sorted by: <strong>{preference}</strong>⌄</span><button onClick={reset}><CircleX /></button></header>{runs.length ? runs.slice(0, 3).map((run, index) => <article className={selected?.id === run.id ? 'is-selected' : ''} key={run.id} onClick={() => setSelected(run)}><div className="quick-reference__vehicle">{run.image ? <img src={run.image} alt=""/> : <BusFront />}<small>{index === 0 ? 'RECOMMENDED' : 'LIVE'}</small></div><div><h3>{run.title || 'Uthenga Transport'}</h3><p>{run.loading_location || run.origin}</p><small>◷ Departs {new Date(run.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} &nbsp; · &nbsp; <em>{run.remaining_seats} of {run.capacity} seats free</em></small>{index === 0 && <small className="quick-reference__why">Recommended — the soonest live departure that fits your group.</small>}</div><aside><b>{run.fare ? `MWK ${Number(run.fare).toLocaleString()}` : 'Fare pending'}</b><small>Per seat</small><strong>{index === 0 ? 'Best Match' : preference}</strong></aside></article>) : <p className="quick-reference__empty">No live Uthenga departure matches this request yet. Adjust the trip details or try again shortly.</p>}</section>}
        {error && <p className="quick-reference__error">{error}</p>}
        <div className="quick-reference__answer"><LockKeyhole /><input value={step < 3 ? values[step] : ''} onChange={event => step < 3 && update(event.target.value)} onKeyDown={event => event.key === 'Enter' && step < 3 && next()} placeholder="Type your answer…"/><button onClick={() => step < 3 ? next() : step === 3 ? discover() : reset()}><Send /></button></div>
      </section>
      <aside className="quick-reference__right">
        <article className={'quick-reference__map' + (manualSelectionEnabled ? ' is-selecting-location' : '')}><CustomerMap location={location} peerLocation={driverLocation ? { latitude: driverLocation.latitude, longitude: driverLocation.longitude } : null} route={route} pickup={selected?.pickup_coordinates} mapsConfig={boot.maps} manualSelectionEnabled={manualSelectionEnabled} onManualLocation={chooseManualLocation}/>{selected && <div className="quick-reference__map-label"><b>{driverLocation ? 'Driver location live' : selected.loading_location || selected.origin}</b><span>{driverLocation ? `Updated ${new Date(driverLocation.captured_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : route ? `${Math.round(route.distance_m / 1000 * 10) / 10} km road route · ${Math.ceil(route.duration_seconds / 60)} min` : 'Verified boarding pickup'}</span></div>}{manualSelectionEnabled && <div className="manual-location-instruction">Click your exact location on the map to use it for this trip.</div>}</article>
        {selected && <article className="quick-reference__departure"><header><b>DEPARTURE SELECTED</b><em>● LIVE</em></header><div><span><BusFront /></span><p><b>{selected.title || 'Uthenga Transport'}</b><small>Driver: verified Uthenga transport provider</small><small>Seats left: {selected.remaining_seats}</small></p><strong><small>Departs at</small>{departureTime}</strong></div>{isWalking && <DepartureCountdown departureAt={selected.planned_departure_at} />}<button disabled={Boolean(sessionId)} onClick={() => void startBoardingRequest()}>{sessionId ? 'Boarding request active' : 'Request boarding'}</button></article>}
        <article className="quick-reference__journey"><Footprints /><div><b>GETTING THERE</b><h3>{isWalking && walkProgressPercent !== null ? `${walkProgressPercent}% of the way there` : distanceText}</h3><p>{isWalking && route ? `About ${Math.ceil(route.duration_seconds / 60)} min remaining` : route ? `Estimated travel time: ${Math.ceil(route.duration_seconds / 60)} min` : 'Use the map control to refine your location.'}</p><i><span style={{ width: `${isWalking && walkProgressPercent !== null ? walkProgressPercent : route ? 68 : 20}%` }} /></i></div><em>{isWalking ? (walkAdvisory ? (walkAdvisory.type === 'stopped' ? 'Stopped?' : 'Delayed') : 'Walking') : route ? 'Good progress!' : 'Location needed'}</em></article>
        <article className={'quick-reference__advice' + (walkAdvisory ? ' has-alert' : '')}><header><b>✦ &nbsp;AI ADVICE</b><span><CloudRain /> 26°C</span></header><p>{walkAdvisory ? walkAdvisory.message : selected ? 'Review the selected departure, then request boarding. The driver must accept before your seat is held.' : 'Tell me where you are heading and I’ll find verified, live transport.'}</p>{walkAdvisory && <div className="quick-reference__advice-actions"><button onClick={() => void callDriver()}><Phone size={13} /> Call Driver</button></div>}</article>
        {sessionId && <CoordinationSessionPanel boot={boot} sessionId={sessionId} onUpdate={setLiveCoordination} onClose={() => { setSessionId(''); setLiveCoordination(null); sessionStorage.removeItem('uthenga.coordination.customer_session'); }} />}
      </aside>
    </div>
    <footer className="quick-reference__actions"><button disabled={!sessionId} onClick={() => void callDriver()}><Phone />Call Driver</button><button disabled={!sessionId} onClick={() => void cancelTrip()}><CircleX />Cancel Trip</button><button onClick={() => void shareTrip()}><Share2 />Share Trip</button><Link to="/assistant"><Headphones />Need Help?</Link>{shareStatus && <small className="quick-reference__share-status">{shareStatus}</small>}</footer>
    {showHistory && <TripHistoryPanel onClose={() => setShowHistory(false)} />}
    {showInbox && <CustomerInboxPanel boot={boot} onClose={() => setShowInbox(false)} />}
  </main>;
}

type CustomerTripHistoryItem = {
  id: string; status: string; passenger_count: number; loading_location: string; requested_at: string;
  boarded_at: string | null; cancelled_at: string | null; cancellation_reason: string | null; completed_at: string | null; run_status: string;
  receipt: { amount: number; method: string; paid_at: string | null } | null;
};
const HISTORY_STATUS_LABELS: Record<string, string> = {
  BOARDED: 'Boarded', BOARDING_REQUESTED: 'Boarding requested', ARRIVED_AT_PICKUP: 'Arrived at pickup', CUSTOMER_EN_ROUTE: 'On the way',
  ACCEPTED: 'Accepted', PENDING_VENDOR: 'Awaiting driver', CUSTOMER_CANCELLED: 'Cancelled', DECLINED: 'Declined', EXPIRED: 'Expired', NO_SHOW: 'No-show',
};

// A real receipt history built from this customer's own Quick Taxi sessions
// and their payment records — not a fabricated "download PDF" archive (no
// document storage exists in this codebase for Quick Taxi).
function TripHistoryPanel({ onClose }: { onClose: () => void }) {
  const [trips, setTrips] = useState<CustomerTripHistoryItem[] | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ coordination: { trips: CustomerTripHistoryItem[] } }>('coordination/history.php')
      .then(result => setTrips(result.coordination.trips))
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Trip history is unavailable.'));
  }, []);

  return <div className="quick-reference__history-overlay" onMouseDown={onClose}>
    <div className="quick-reference__history-panel" onMouseDown={event => event.stopPropagation()}>
      <header><b>Trip History</b><button onClick={onClose}>×</button></header>
      {error && <p className="quick-reference__error">{error}</p>}
      {!trips && !error && <p className="quick-reference__history-empty">Loading your trips…</p>}
      {trips && trips.length === 0 && <p className="quick-reference__history-empty">No Quick Taxi trips yet. Once you complete one, it'll show up here with its receipt.</p>}
      {trips?.map(trip => <div className="quick-reference__history-item" key={trip.id}>
        <div className="row1"><b>{trip.loading_location}</b><span className={`status ${trip.receipt ? 'is-paid' : ''}`}>{trip.receipt ? 'Paid' : (HISTORY_STATUS_LABELS[trip.status] || trip.status)}</span></div>
        <p>{new Date(trip.requested_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })} · {trip.passenger_count} passenger{trip.passenger_count === 1 ? '' : 's'}</p>
        {trip.receipt && <p>MWK {trip.receipt.amount.toLocaleString()} · {trip.receipt.method === 'cash' ? 'Cash' : trip.receipt.method === 'mobile_money' ? 'Mobile Money' : 'Bank'}{trip.receipt.paid_at ? ` · ${new Date(trip.receipt.paid_at).toLocaleDateString()}` : ''}</p>}
        {trip.cancellation_reason && <p>Cancelled: {trip.cancellation_reason}</p>}
      </div>)}
    </div>
  </div>;
}

type DirectThreadSummary = { id: string; peer_name: string; last_message_at: string | null; last_message_preview: string | null; unread_count: number };
type DirectMessageItem = { id: string; sender_role: 'vendor' | 'customer'; body: string; created_at: string };
type DirectThreadDetail = { thread_id: string; viewer_role: 'vendor' | 'customer'; peer_name: string; messages: DirectMessageItem[] };

// A persistent 1-1 thread with a driver who has actually carried this
// customer — independent of any single trip, so a driver can still reach
// them afterwards (e.g. a forgotten bag). A customer replies into a thread a
// driver started; they don't start new ones from here. See Messaging.php.
function CustomerInboxPanel({ boot, onClose }: { boot: Boot; onClose: () => void }) {
  const [threads, setThreads] = useState<DirectThreadSummary[] | null>(null);
  const [selectedThreadId, setSelectedThreadId] = useState('');
  const [detail, setDetail] = useState<DirectThreadDetail | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const historyRef = useRef<HTMLDivElement | null>(null);

  const loadThreads = () => api<{ result: { threads: DirectThreadSummary[] } }>('direct-message/inbox.php')
    .then(response => { setThreads(response.result.threads); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Your inbox is unavailable.'));

  useEffect(() => { void loadThreads(); const interval = window.setInterval(loadThreads, 5_000); return () => window.clearInterval(interval); }, []);

  useEffect(() => {
    if (!selectedThreadId) { setDetail(null); return; }
    const load = () => api<{ result: DirectThreadDetail }>(`direct-message/thread.php?thread_id=${encodeURIComponent(selectedThreadId)}`)
      .then(response => { setDetail(response.result); setError(''); })
      .catch(reason => setError(reason instanceof Error ? reason.message : 'This conversation is unavailable.'));
    void load();
    void api('direct-message/action.php', 'POST', { action: 'mark_read', thread_id: selectedThreadId }, boot.csrf_token).catch(() => undefined);
    const interval = window.setInterval(load, 4_000);
    return () => window.clearInterval(interval);
  }, [selectedThreadId, boot.csrf_token]);
  useEffect(() => { if (historyRef.current) historyRef.current.scrollTop = historyRef.current.scrollHeight; }, [detail?.messages.length]);

  const send = async () => {
    const body = message.trim(); if (!body || !selectedThreadId) return; setMessage('');
    try {
      const response = await api<{ result: DirectThreadDetail }>('direct-message/action.php', 'POST', { action: 'send', thread_id: selectedThreadId, body }, boot.csrf_token);
      setDetail(response.result); void loadThreads();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Message could not be sent.'); }
  };

  return <div className="quick-reference__history-overlay" onMouseDown={onClose}>
    <div className="quick-reference__history-panel" onMouseDown={event => event.stopPropagation()}>
      <header>
        {selectedThreadId ? <button className="quick-reference__inbox-back" onClick={() => setSelectedThreadId('')}>← {detail?.peer_name || 'Messages'}</button> : <b>Messages</b>}
        <button onClick={onClose}>×</button>
      </header>
      {error && <p className="quick-reference__error">{error}</p>}
      {!selectedThreadId && <>
        {!threads && !error && <p className="quick-reference__history-empty">Loading your messages…</p>}
        {threads && threads.length === 0 && <p className="quick-reference__history-empty">No direct messages yet. A driver you've ridden with can start a conversation here.</p>}
        {threads?.map(t => <button key={t.id} className="quick-reference__inbox-row" onClick={() => setSelectedThreadId(t.id)}>
          <span className="row1"><b>{t.peer_name}</b>{t.unread_count > 0 && <em>{t.unread_count}</em>}</span>
          <p>{t.last_message_preview || 'No messages yet'}</p>
        </button>)}
      </>}
      {selectedThreadId && <div className="quick-reference__inbox-thread">
        <div className="quick-reference__inbox-history" ref={historyRef}>
          {detail && detail.messages.length === 0 && <p className="quick-reference__history-empty">No messages yet.</p>}
          {detail?.messages.map(m => <p key={m.id} className={`quick-reference__inbox-bubble ${m.sender_role === 'customer' ? 'is-mine' : ''}`}><span>{m.body}</span><time>{new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></p>)}
        </div>
        <div className="quick-reference__inbox-input"><input value={message} maxLength={1000} placeholder={`Message ${detail?.peer_name || 'driver'}`} onChange={event => setMessage(event.target.value)} onKeyDown={event => event.key === 'Enter' && void send()} /><button onClick={() => void send()}><Send size={15} /></button></div>
      </div>}
    </div>
  </div>;
}

function TravelWorkspace({ boot }: { boot: Boot }) {
  const prompts = [
    ['Where are you heading today?', 'Mzuzu, Area 25, Blantyre…'],
    ['When would you like to leave?', 'Today around 3 PM'],
    ['How many people are travelling?', '1'],
  ];
  const [step, setStep] = useState(0);
  const [values, setValues] = useState(['', '', '1']);
  const [preference, setPreference] = useState('Most Comfortable');
  const [location, setLocation] = useState<Coordinates | null>(null);
  const [runs, setRuns] = useState<Run[]>([]);
  const [working, setWorking] = useState(false);
  const [error, setError] = useState('');

  const destination = values[0].trim();
  async function advance() {
    if (!values[step]?.trim()) return setError('Please provide this trip detail to continue.');
    setError(''); setStep(current => current + 1);
  }
  async function discover() {
    if (!destination) return setError('Tell Uthenga where you are heading first.');
    setWorking(true); setError('');
    try {
      const currentLocation = location || await foregroundLocation();
      setLocation(currentLocation);
      const result = await api<{ discovery: { runs: Run[] } }>('coordination/discover.php', 'POST', {
        destination,
        passenger_count: Math.max(1, Number.parseInt(values[2], 10) || 1),
        location: currentLocation,
        preference,
      }, boot.csrf_token);
      setRuns(result.discovery.runs || []);
      setStep(4);
    } catch (requestError) { setError(requestError instanceof Error ? requestError.message : 'Unable to find departures.'); }
    finally { setWorking(false); }
  }
  const prompt = prompts[step];
  return <main className="agent-workspace">
    <header className="workspace-header"><a className="brand" href={boot.legacy_fallbacks?.customer_workspace}>● UTHENGA <span>AGENT</span></a><div className="presence"><i /> Secure session · {boot.user?.name}</div>{boot.user?.role === 'vendor' && <Link to="/driver">Driver operations ↗</Link>}</header>
    {step < 3 && prompt && <section className="terminal-card"><p className="eyebrow">TRAVEL COORDINATION</p><h1>{prompt[0]}</h1><label className="terminal-input"><span>›</span><input autoFocus value={values[step]} onChange={event => setValues(previous => previous.map((value, index) => index === step ? event.target.value : value))} onKeyDown={event => event.key === 'Enter' && advance()} placeholder={prompt[1]} /></label><button className="text-action" onClick={advance}>Continue <span>↵</span></button>{error && <p className="inline-error">{error}</p>}<p className="privacy-note">Live travel coordination uses your current foreground location. Uthenga does not begin background tracking.</p></section>}
    {step === 3 && <section className="terminal-card preference-card"><p className="eyebrow">TRAVEL PREFERENCE</p><h1>Do you prefer</h1><div className="preference-list">{['Cheapest', 'Fastest', 'Most Comfortable'].map(option => <button key={option} className={preference === option ? 'choice choice--selected' : 'choice'} onClick={() => setPreference(option)}><i />{option}</button>)}</div><button className="button button--primary" onClick={discover} disabled={working}>{working ? 'Verifying location and departures…' : 'Find live travel'}</button>{error && <p className="inline-error">{error}</p>}</section>}
    {step === 4 && <section className="result-workspace"><div className="result-header"><div><p className="eyebrow">LIVE UTHENGA DEPARTURES</p><h1>{runs.length ? 'Your travel workspace is ready.' : 'No matching live departure.'}</h1><p>{destination} · {values[1]} · {values[2]} traveller{values[2] === '1' ? '' : 's'} · {preference}</p></div><button className="button button--quiet" onClick={() => { setStep(0); setRuns([]); }}>Refine trip</button></div><div className="signal-row"><span className="signal signal--done">Location verified</span><span className="signal signal--done">Live runs checked</span><span className="signal">Driver confirmation required</span></div>{runs.length ? <div className="trip-grid">{runs.map(run => <article className="run-card" key={run.id}><p className="eyebrow">LIVE · {run.status || 'LOADING'}</p><h2>{run.origin} <span>→</span> {run.destination}</h2><p>{run.title || 'Uthenga transport'} · departs {new Date(run.planned_departure_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p><dl><div><dt>Seats</dt><dd>{run.remaining_seats}/{run.capacity}</dd></div><div><dt>Fare</dt><dd>{run.fare ? `MWK ${Number(run.fare).toLocaleString()}` : 'Confirm with driver'}</dd></div></dl><button className="button button--primary" onClick={() => void requestSeat(run, boot, location, values, setError)}>Request seat</button></article>)}</div> : <div className="empty-card"><h2>Nothing has left for that request yet.</h2><p>There are no active Uthenga sessions that match it. Try a nearby destination or check again shortly.</p></div>}{error && <p className="inline-error">{error}</p>}</section>}
  </main>;
}

async function requestSeat(run: Run, boot: Boot, location: Coordinates | null, values: string[], setError: (message: string) => void): Promise<string | null> {
  try {
    const currentLocation = location || await foregroundLocation();
    const response = await api<{ result: CoordinationSession }>('coordination/action.php', 'POST', { action: 'request_seat', run_id: run.id, passenger_count: Math.max(1, Number.parseInt(values[2], 10) || 1), destination: values[0]?.trim() || undefined, location: currentLocation }, boot.csrf_token);
    const sessionId = response.result?.session?.id || null;
    if (!sessionId) throw new Error('Uthenga could not open the live coordination session.');
    return sessionId;
  } catch (error) { setError(error instanceof Error ? error.message : 'Unable to send the seat request.'); return null; }
}

type CallPhase = 'idle' | 'connecting' | 'connected' | 'failed';
const ICE_SERVERS: RTCIceServer[] = [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];
function formatCallClock(totalSeconds: number): string { return `${String(Math.floor(totalSeconds / 60)).padStart(2, '0')}:${String(totalSeconds % 60).padStart(2, '0')}`; }

// Uthenga-mediated in-app audio calling: no phone number is ever exchanged.
// The call itself flows peer-to-peer over WebRTC once both sides accept;
// Uthenga only relays signaling (offer/answer/ICE) and never sees the media.
function CallPanel({ boot, sessionId, call, allowedActions, viewerRole, peerLabel, onRefresh }: {
  boot: Boot; sessionId: string; call: CallState; allowedActions: string[]; viewerRole: 'customer' | 'vendor'; peerLabel: string; onRefresh: () => void;
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
    try { await api('coordination/action.php', 'POST', { action: 'call_signal', call_request_id: call.call_request_id, kind, payload }, boot.csrf_token); }
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

  useEffect(() => {
    if (call.state !== 'ACCEPTED' || !call.call_request_id) return;
    let disposed = false;
    const poll = async () => {
      try {
        const response = await api<{ result: { signals: CallSignal[] } }>(`coordination/call-signals.php?call_request_id=${encodeURIComponent(call.call_request_id!)}&since_id=${sinceIdRef.current}`);
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
    try { await api('coordination/action.php', 'POST', { action: 'request_call', session_id: sessionId }, boot.csrf_token); onRefresh(); }
    catch { /* surfaced by the parent panel's own notice state */ } finally { setBusy(false); }
  };
  const decide = async (decision: 'ACCEPT' | 'DECLINE') => {
    if (!call.call_request_id) return; setBusy(true);
    try { await api('coordination/action.php', 'POST', { action: 'decide_call', call_request_id: call.call_request_id, decision }, boot.csrf_token); onRefresh(); }
    catch { /* best-effort */ } finally { setBusy(false); }
  };
  const hangup = async () => { if (call.call_request_id) await postSignal('hangup', {}); teardown(); onRefresh(); };

  if (call.state === 'NONE' || dismissed) {
    if (!allowedActions.includes('REQUEST_CALL')) return null;
    return <button className="coordination-panel__call-start" disabled={busy} onClick={() => void startCall()}><Phone size={14} /> Call {peerLabel}</button>;
  }

  return <div className="coordination-panel__call-panel">
    <audio ref={remoteAudioRef} autoPlay />
    {call.state === 'RINGING_OUT' && <>
      <div className="coordination-panel__call-status"><PhoneOutgoing size={20} /><b>Calling {call.peer_name || peerLabel}…</b><small>Waiting for an answer</small></div>
      <button className="coordination-panel__call-hangup" onClick={() => void hangup()}><PhoneOff size={14} /> Cancel</button>
    </>}
    {call.state === 'RINGING_IN' && <>
      <div className="coordination-panel__call-status"><PhoneIncoming size={20} /><b>Incoming call from {call.peer_name || peerLabel}</b></div>
      <div className="coordination-panel__call-row">
        <button className="coordination-panel__call-answer" disabled={busy} onClick={() => void decide('ACCEPT')}><Phone size={14} /> Answer</button>
        <button className="coordination-panel__call-hangup" disabled={busy} onClick={() => void decide('DECLINE')}><PhoneOff size={14} /> Decline</button>
      </div>
    </>}
    {call.state === 'ACCEPTED' && <>
      <div className="coordination-panel__call-status">
        {phase === 'failed'
          ? <><PhoneOff size={20} /><b>Call could not connect</b><small>{micError || 'This network could not establish a direct audio connection.'}</small></>
          : <><Phone size={20} /><b>{call.peer_name || peerLabel}</b><small>{phase === 'connected' ? formatCallClock(elapsed) : 'Connecting…'}</small></>}
      </div>
      <button className="coordination-panel__call-hangup" onClick={() => void hangup()}><PhoneOff size={14} /> Hang Up</button>
    </>}
    {(call.state === 'DECLINED' || call.state === 'CANCELLED' || call.state === 'ENDED') && <div className="coordination-panel__call-status coordination-panel__call-status--ended"><PhoneOff size={20} /><b>{call.state === 'DECLINED' ? 'Call declined' : call.state === 'CANCELLED' ? 'Call cancelled' : 'Call ended'}</b></div>}
  </div>;
}

type TransportPayment = { state: 'NONE' | 'PENDING' | 'CHECKOUT_READY' | 'VERIFYING' | 'PAID' | 'FAILED' | 'CASH_PENDING'; amount: number | null; currency: string | null; method: string | null; checkout_url: string | null; confirmed_by: string | null; confirmed_at: string | null };

// Boarding is confirmed only after the driver verifies physical presence
// (see CoordinationService::confirmBoarding()). Payment only becomes
// available at that point — never a UI-only "I paid" click. The backend is
// always the authority: this card only ever reflects transport-payment/
// status.php's server state, and polls until it changes.
function PaymentCard({ boot, sessionId }: { boot: Boot; sessionId: string }) {
  const [payment, setPayment] = useState<TransportPayment | null>(null);
  const [method, setMethod] = useState<'mobile_money' | 'bank'>('mobile_money');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = () => api<{ result: { payment: TransportPayment } }>(`transport-payment/status.php?session_id=${encodeURIComponent(sessionId)}`)
    .then(response => setPayment(response.result.payment))
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Payment status is unavailable.'));

  useEffect(() => { void load(); const interval = window.setInterval(load, 4_000); return () => window.clearInterval(interval); }, [sessionId]);

  const pay = async () => {
    setBusy(true); setError('');
    try {
      const response = await api<{ result: { payment: TransportPayment } }>('transport-payment/start.php', 'POST', { session_id: sessionId, method }, boot.csrf_token);
      setPayment(response.result.payment);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Payment could not be started.'); }
    finally { setBusy(false); }
  };

  if (!payment) return null;
  if (payment.state === 'PAID') return <div className="coordination-panel__payment coordination-panel__payment--paid"><Check size={16} /><span>Paid{payment.amount !== null ? ` MWK ${payment.amount.toLocaleString()}` : ''}{payment.method ? ` · ${payment.method === 'cash' ? 'Cash' : payment.method === 'mobile_money' ? 'Mobile Money' : 'Bank'}` : ''}</span></div>;

  return <div className="coordination-panel__payment">
    <b>Pay for this trip with Uthenga Payment</b>
    {(payment.state === 'CHECKOUT_READY' || payment.state === 'VERIFYING') ? <>
      <p>Waiting for your payment to complete.</p>
      {payment.checkout_url && <a className="coordination-panel__pay-btn" href={payment.checkout_url} target="_blank" rel="noopener noreferrer">Continue to payment</a>}
    </> : <>
      <div className="coordination-panel__pay-methods">
        <label><input type="radio" checked={method === 'mobile_money'} onChange={() => setMethod('mobile_money')} /> Mobile Money</label>
        <label><input type="radio" checked={method === 'bank'} onChange={() => setMethod('bank')} /> Bank</label>
      </div>
      <button className="coordination-panel__pay-btn" disabled={busy} onClick={() => void pay()}>{busy ? 'Starting…' : 'Pay now'}</button>
    </>}
    {error && <small className="coordination-panel__notice">{error}</small>}
  </div>;
}

// Deterministic, state-driven guidance — never an LLM call, never a fact the
// backend hasn't already computed. The customer-side analogue of the
// driver's operations-assistant strip: "AI provides contextual guidance"
// without ever risking an invented ETA, position, or payment fact.
function customerGuidance(coordination: CoordinationSession | null): string {
  if (!coordination) return '';
  const session = coordination.session;
  const runStatus = session.run?.status;
  if (session.status === 'PENDING_VENDOR') return "Your request is with the driver — we'll update this the moment they respond.";
  if (session.status === 'ACCEPTED') return "Head to the pickup point when you're ready, then let the driver know you're on your way.";
  if (session.status === 'CUSTOMER_EN_ROUTE') return "Let the driver know once you've arrived at the pickup point.";
  if (session.status === 'ARRIVED_AT_PICKUP') return "You're at the pickup point — let the driver know once you're safely on board.";
  if (session.status === 'BOARDING_REQUESTED') return "Waiting for the driver to confirm you're on board.";
  if (session.status === 'BOARDED' && runStatus === 'TRAVELLING') return "Your trip is under way — the driver's live location updates automatically on the map.";
  if (session.status === 'BOARDED') return 'Boarding is confirmed. Complete payment below to finish getting ready for departure.';
  return '';
}

function CoordinationSessionPanel({ boot, sessionId, onClose, onUpdate, compact = false }: { boot: Boot; sessionId: string; onClose?: () => void; onUpdate?: (coordination: CoordinationSession) => void; compact?: boolean }) {
  const [coordination, setCoordination] = useState<CoordinationSession | null>(null);
  const [message, setMessage] = useState('');
  const [notice, setNotice] = useState('');
  const [pendingMessages, setPendingMessages] = useState<CoordinationSession['messages']>([]);
  const historyRef = useRef<HTMLDivElement | null>(null);
  const load = async () => {
    try {
      const result = await api<{ coordination: CoordinationSession }>(`coordination/session.php?session_id=${encodeURIComponent(sessionId)}`);
      setCoordination(result.coordination); onUpdate?.(result.coordination);
      // Drop optimistic echoes once the real row shows up in a poll.
      setPendingMessages(current => current.filter(pending => !result.coordination.messages.some(item => item.sender_role === pending.sender_role && item.body === pending.body)));
    } catch (error) { setNotice(error instanceof Error ? error.message : 'The live travel session is unavailable.'); }
  };
  useEffect(() => { void load(); const interval = window.setInterval(() => void load(), 3_000); return () => window.clearInterval(interval); }, [sessionId]);
  const allMessages = useMemo(() => [...(coordination?.messages || []), ...pendingMessages], [coordination, pendingMessages]);
  useEffect(() => { if (historyRef.current) historyRef.current.scrollTop = historyRef.current.scrollHeight; }, [allMessages.length]);
  const send = async () => {
    const body = message.trim();
    if (!body || !coordination) return;
    const optimistic = { id: `pending-${Date.now()}`, sender_role: coordination.viewer_role, body, created_at: new Date().toISOString() };
    setPendingMessages(current => [...current, optimistic]); setMessage('');
    try { await api('coordination/action.php', 'POST', { action: 'message', session_id: sessionId, body }, boot.csrf_token); await load(); }
    catch (error) { setNotice(error instanceof Error ? error.message : 'Message could not be sent.'); setPendingMessages(current => current.filter(item => item.id !== optimistic.id)); }
  };
  const action = async (next: string) => {
    try { await api('coordination/action.php', 'POST', { action: 'customer_action', session_id: sessionId, customer_action: next }, boot.csrf_token); await load(); }
    catch (error) { setNotice(error instanceof Error ? error.message : 'That travel update is no longer available.'); }
  };
  const workspace = coordination?.workspace;
  const allowed = workspace?.allowed_actions || [];
  const isCustomer = coordination?.viewer_role === 'customer';
  return <article className={'coordination-panel' + (compact ? ' coordination-panel--compact' : '')}>
    <header><div><b>{workspace?.state?.replaceAll('_', ' ') || 'LIVE TRIP UPDATE'}</b><small>Updates automatically while this secure tab is open.</small></div>{onClose && <button onClick={onClose}>×</button>}</header>
    <p className="coordination-panel__message">{workspace?.message || 'Connecting to your live transport session…'}</p>
    {coordination && customerGuidance(coordination) && <p className="coordination-panel__guidance"><Sparkles size={13} /> {customerGuidance(coordination)}</p>}
    {coordination?.session.run && <p className="coordination-panel__route">{coordination.session.run.origin} <span>→</span> {coordination.session.run.destination}</p>}
    {coordination?.latest_locations?.some(item => item.actor === 'vendor') && <p className="coordination-panel__location">Driver location received for this session.</p>}
    {isCustomer && allowed.length > 0 && <div className="coordination-panel__actions">
      {allowed.includes('EN_ROUTE') && <button onClick={() => void action('EN_ROUTE')}>I’m on my way</button>}
      {allowed.includes('ARRIVED_AT_PICKUP') && <button onClick={() => void action('ARRIVED_AT_PICKUP')}>I’ve arrived</button>}
      {allowed.includes('BOARDED') && <button onClick={() => void action('BOARDED')}>I’m On Board</button>}
      {allowed.includes('CANCEL') && <button className="is-danger" onClick={() => void action('CANCEL')}>Cancel request</button>}
    </div>}
    {coordination && <CallPanel boot={boot} sessionId={sessionId} call={coordination.call} allowedActions={allowed} viewerRole={coordination.viewer_role} peerLabel="Driver" onRefresh={() => void load()} />}
    {isCustomer && coordination?.session.status === 'BOARDED' && <PaymentCard boot={boot} sessionId={sessionId} />}
    {allowed.includes('MESSAGE') && coordination && <div className="coordination-panel__messages"><div className="coordination-panel__history" ref={historyRef}>{allMessages.length ? allMessages.map(item => <p key={item.id} className={item.sender_role === coordination.viewer_role ? 'is-mine' : ''}><span>{item.body}</span><time>{new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></p>) : <small>No messages yet. Use this channel for this live trip only.</small>}</div><div><input value={message} maxLength={1000} placeholder="Message driver" onChange={event => setMessage(event.target.value)} onKeyDown={event => event.key === 'Enter' && void send()} /><button onClick={() => void send()}>Send</button></div></div>}
    {notice && <small className="coordination-panel__notice">{notice}</small>}
  </article>;
}

function DriverLifecycleWorkspace({ boot }: { boot: Boot }) {
  const [profile, setProfile] = useState<TransportProfile | null>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const load = async () => {
    try { const response = await api<{ vendor_profiles: { profiles: TransportProfile[] } }>('vendor/profiles.php'); setProfile(response.vendor_profiles.profiles.find(item => item.type === 'transport') || null); }
    catch (error) { setNotice(error instanceof Error ? error.message : 'Unable to load this service lifecycle.'); }
  };
  useEffect(() => { void load(); }, []);
  const perform = async (action: string) => {
    setBusy(true); setNotice('');
    try {
      const body: Record<string, unknown> = { action };
      if (profile) body.profile_id = profile.id;
      if (action === 'create_draft') { body.profile_type = 'transport'; body.profile_name = 'My transport service'; }
      await api('vendor/profiles.php', 'POST', body, boot.csrf_token);
      await load(); setNotice(action === 'submit_for_review' ? 'Submitted for review. Uthenga will notify you of the outcome.' : action === 'pause_profile' ? 'Service paused. It is no longer available to customers.' : action === 'archive_profile' ? 'Service archived.' : action === 'activate_profile' ? 'This is now your active Driver Operations service.' : 'Private transport draft created. Complete Settings next.');
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Uthenga could not update this service.'); }
    finally { setBusy(false); }
  };
  const status = profile?.status || 'NEW';
  return <main className="driver-settings driver-lifecycle"><header><Link to="/driver">← Driver dashboard</Link><span>{status}</span></header><section><p className="eyebrow">DRIVER OPERATIONS · SERVICE LIFECYCLE</p><h1>Release a service safely.</h1><p className="settings-intro">A driver service moves from a private setup through review to publication. Nothing becomes public or bookable merely because a setting was saved.</p><div className="lifecycle-track">{['NEW','PRIVATE_DRAFT','SETUP_INCOMPLETE','READY_FOR_REVIEW','PUBLISHED','ACTIVE','PAUSED','ARCHIVED'].map(state => <span key={state} className={state === status ? 'is-current' : ''}>{state.replaceAll('_', ' ')}</span>)}</div>{profile ? <article className="empty-card"><p className="eyebrow">CURRENT TRANSPORT SERVICE</p><h2>{profile.name}</h2><p>Status: <b>{profile.status}</b>{profile.active ? ' · selected for Driver Operations' : ''}</p><div className="lifecycle-actions">{['PRIVATE_DRAFT','SETUP_INCOMPLETE'].includes(status) && <><Link className="button button--quiet" to="/driver/settings">Complete settings</Link><button className="button button--primary" disabled={busy} onClick={() => void perform('submit_for_review')}>Submit for review</button></>}{status === 'READY_FOR_REVIEW' && <p className="muted">Awaiting an authorised review. The service remains private until an approval outcome.</p>}{['PUBLISHED','PAUSED'].includes(status) && <button className="button button--primary" disabled={busy} onClick={() => void perform('activate_profile')}>Activate Driver Operations</button>}{['PUBLISHED','ACTIVE'].includes(status) && <button className="button button--quiet" disabled={busy} onClick={() => void perform('pause_profile')}>Pause service</button>}{status !== 'ARCHIVED' && <button className="button button--quiet" disabled={busy} onClick={() => void perform('archive_profile')}>Archive service</button>}</div></article> : <article className="empty-card"><h2>No transport profile yet.</h2><p>Create a private profile, then use Settings to add the verified vehicle, route, capacity, timetable, fare, and driver details.</p><button className="button button--primary" disabled={busy} onClick={() => void perform('create_draft')}>Create private transport draft</button></article>}{notice && <p className={notice.includes('could not') || notice.includes('Unable') ? 'inline-error' : 'settings-notice'}>{notice}</p>}</section></main>;
}

function DriverSessionComposer({ boot }: { boot: Boot }) {
  const minimum = new Date(Date.now() + 5 * 60_000).toISOString().slice(0, 16);
  const [options, setOptions] = useState<DriverSessionOption[]>([]);
  const [selected, setSelected] = useState('');
  const [departureAt, setDepartureAt] = useState(minimum);
  const [remainingSeats, setRemainingSeats] = useState('');
  const [loadingLocation, setLoadingLocation] = useState('');
  const [driverNote, setDriverNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const current = options.find(option => option.profile_id === selected) || null;
  const load = async () => {
    try {
      const response = await api<{ result: { options: DriverSessionOption[] } }>('vendor/profiles.php', 'POST', { action: 'transport_session_options' }, boot.csrf_token);
      const next = response.result.options || []; setOptions(next);
      if (next[0]) { setSelected(next[0].profile_id); setRemainingSeats(String(next[0].capacity)); setLoadingLocation(next[0].loading_location); }
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Unable to load your published transport services.'); }
  };
  useEffect(() => { void load(); }, []);
  const choose = (profileId: string) => { const option = options.find(item => item.profile_id === profileId); setSelected(profileId); if (option) { setRemainingSeats(String(option.capacity)); setLoadingLocation(option.loading_location); } };
  const create = async (event: React.FormEvent) => {
    event.preventDefault(); if (!current) return; setBusy(true); setNotice('');
    try {
      await api('coordination/action.php', 'POST', { action: 'create_run', service_id: current.service_id, seat_class_id: current.seat_class_id, remaining_seats: Number(remainingSeats), planned_departure_at: new Date(departureAt).toISOString(), loading_location: loadingLocation, driver_note: driverNote }, boot.csrf_token);
      setNotice('Live departure opened in loading mode. You can now accept passenger requests.');
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Uthenga could not open this departure.'); }
    finally { setBusy(false); }
  };
  return <main className="driver-settings driver-session-composer"><header><Link to="/driver">← Driver dashboard</Link><span>LIVE DEPARTURE</span></header><section><p className="eyebrow">DRIVER OPERATIONS · SESSION</p><h1>Open a live departure.</h1><p className="settings-intro">This is a temporary loading session, not a booking. Only one active session can exist for a driver at a time, and all capacity remains verified by Uthenga.</p>{options.length === 0 ? <div className="empty-card"><h2>Publish a transport service first.</h2><p>Finish Driver Settings, submit the completed service for review, then activate its published profile before opening a live departure.</p><Link className="button button--primary" to="/driver/settings">Open Driver Settings</Link></div> : <form className="settings-form" onSubmit={create}><fieldset><legend>Published service</legend><label className="wide">Service<select value={selected} onChange={event => choose(event.target.value)}>{options.map(option => <option key={option.profile_id} value={option.profile_id}>{option.name} · {option.origin} → {option.destination}{option.active ? ' · Active' : ''}</option>)}</select></label>{current && <p className="settings-intro">Capacity {current.capacity} · fare MWK {Number(current.fare).toLocaleString()} · inventory available {current.inventory_remaining}</p>}</fieldset><fieldset><legend>Loading details</legend><label>Departure date and time<input type="datetime-local" required min={minimum} value={departureAt} onChange={event => setDepartureAt(event.target.value)} /></label><label>Seats physically free<input type="number" required min="1" max={current?.capacity || 500} value={remainingSeats} onChange={event => setRemainingSeats(event.target.value)} /></label><label className="wide">Loading point<input required value={loadingLocation} onChange={event => setLoadingLocation(event.target.value)} /></label><label className="wide">Operational note<textarea rows={3} value={driverNote} onChange={event => setDriverNote(event.target.value)} placeholder="Optional note for your own driver operations." /></label></fieldset><div className="settings-save"><span>Opening this session starts Loading mode. Change to Travelling only once you are departing.</span><button className="button button--primary" disabled={busy}>{busy ? 'Opening verified departure…' : 'Open live departure'}</button></div></form>}{notice && <p className={notice.startsWith('Live') ? 'settings-notice' : 'inline-error'}>{notice}</p>}</section></main>;
}

function DriverOperations({ boot }: { boot: Boot }) {
  const [queue, setQueue] = useState<Queue>({ active_run: null, sessions: [] });
  const [error, setError] = useState('');
  const load = () => api<{ coordination: Queue }>('coordination/vendor-queue.php').then(result => setQueue(result.coordination)).catch(reason => setError(reason.message));
  useEffect(() => { load(); const interval = window.setInterval(load, 15_000); return () => window.clearInterval(interval); }, []);
  const run = queue.active_run;
  const pending = useMemo(() => queue.sessions.filter(session => session.status === 'PENDING_VENDOR'), [queue]);
  const accepted = useMemo(() => queue.sessions.filter(session => session.status !== 'PENDING_VENDOR'), [queue]);
  const action = async (payload: Record<string, unknown>) => { try { await api('coordination/action.php', 'POST', payload, boot.csrf_token); await load(); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Action unavailable.'); } };
  return <main className="driver-ops"><aside className="driver-rail"><div className="driver-brand"><UthengaLogoImg size="sm" /><span><small style={{ display: 'block', fontSize: '0.65rem', color: '#44ddaf', marginTop: '2px', fontWeight: 600 }}>DRIVER OPS</small></span></div><div className="driver-avatar">{boot.user?.name?.slice(0, 1) || 'D'}<i /></div><b>{boot.user?.name}</b><small>Driver operations</small><nav>{['Dashboard', 'Trips', 'Passengers', 'Messages', 'Earnings', 'Vehicle', 'Schedule', 'Reports'].map((item, index) => <a className={index === 0 ? 'active' : ''} href={'#' + item.toLowerCase()} key={item}>{item}</a>)}<a href={boot.legacy_fallbacks?.driver_workspace + '#settings'}>Settings ↗</a></nav><a className="back-link" href={boot.legacy_fallbacks?.vendor_workspace}>← Vendor workspace</a><div className="mode-switch"><b>● STATION MODE</b><span>○ Journey mode</span></div></aside><section className="driver-main"><header className="driver-heading"><div><h1>Good morning, {boot.user?.name?.split(' ')[0]} 👋</h1><p>{run ? 'Your active transport session is ready for action.' : 'No active transport session right now.'}</p></div><div className="weather">☀ 26°C <small>{new Date().toLocaleDateString()}</small></div></header>{error && <p className="inline-error">{error}</p>}<div className="driver-layout"><div className="driver-content"><article className="next-departure"><div><p>NEXT DEPARTURE</p><h2>{run ? `${run.origin} → ${run.destination}` : 'No active departure'}</h2><span>{run ? 'Uthenga live transport session' : 'Create or activate a transport session from Settings.'}</span><dl>{run && <><div><dt>Capacity</dt><dd>{run.remaining_seats} / {run.capacity}</dd></div><div><dt>Fare</dt><dd>{run.fare ? `MWK ${Number(run.fare).toLocaleString()}` : '—'}</dd></div></>}</dl></div><div className="countdown"><p>{run?.status === 'TRAVELLING' ? 'TRAVELLING' : 'DEPARTURE STATUS'}</p><strong>{run ? (run.status || 'LOADING') : '—'}</strong><button className="button button--quiet" disabled={!run} onClick={() => run && action({ action: 'update_run', run_id: run.id, status: run.status === 'TRAVELLING' ? 'COMPLETED' : 'TRAVELLING' })}>{run?.status === 'TRAVELLING' ? 'Complete trip' : 'Start travel →'}</button></div></article><div className="metrics"><Metric label="Confirmed" value={accepted.length} accent="green"/><Metric label="Pending" value={pending.length} accent="amber"/><Metric label="Passengers" value={queue.sessions.reduce((total, session) => total + session.passenger_count, 0)} accent="blue"/><Metric label="Seats available" value={run?.remaining_seats ?? 0} accent="purple"/></div><article className="route-stage"><div className="route-line" /><div className="route-bubble route-bubble--one">● {run?.origin || 'Loading point'}</div><div className="route-bubble route-bubble--two">◎ {run?.destination || 'Destination'}</div><p>Route map container</p><small>Routing remains provider-backed; this screen never fabricates a live map or location.</small></article><article className="ops-brief"><div className="assistant-orb">◉</div><div><p>AI OPERATIONS BRIEF <span>● Active</span></p><b>{pending.length ? `${pending.length} passenger request${pending.length > 1 ? 's' : ''} need your decision.` : run ? 'Passenger inbox is clear. Keep boarding status current.' : 'Finish your vehicle and route settings before creating a session.'}</b></div></article></div><aside className="passenger-panel"><header><b>PASSENGERS</b><a href="#passengers">View all</a></header><div className="passenger-tabs"><span>All ({queue.sessions.length})</span><span>Confirmed</span><span>Pending</span></div>{queue.sessions.length ? queue.sessions.map(session => <div className="passenger-row" key={session.id}><div className="mini-avatar">P</div><div><b>{session.passenger_count} passenger{session.passenger_count > 1 ? 's' : ''}</b><small>{session.status.replaceAll('_', ' ')}</small></div>{session.status === 'PENDING_VENDOR' ? <button className="accept" onClick={() => action({ action: 'vendor_decision', session_id: session.id, decision: 'ACCEPT' })}>Accept</button> : <span className="state-ok">Confirmed</span>}</div>) : <p className="muted">No passenger requests yet.</p>}<div className="quick-actions"><button disabled={!run} onClick={() => run && action({ action: 'update_run', run_id: run.id, status: 'LOADING' })}>Start boarding</button><button disabled={!run} onClick={() => run && action({ action: 'update_run', run_id: run.id, status: 'CANCELLED' })}>Report issue</button></div></aside></div></section></main>;
}
type TransportProfile = { id: string; type: string; name: string; active: boolean; status: string; configuration: Record<string, unknown> };
type DriverSettingsForm = { profile_name: string; vehicle_type: string; total_seats: string; origin: string; destination: string; pickup_location: string; departure_time: string; fare_per_seat: string; vehicle_image_url: string; driver_name: string; driver_phone: string; description: string };
const blankDriverSettings: DriverSettingsForm = { profile_name: '', vehicle_type: 'bus', total_seats: '', origin: '', destination: '', pickup_location: '', departure_time: '08:00', fare_per_seat: '', vehicle_image_url: '', driver_name: '', driver_phone: '', description: '' };

function DriverSettings({ boot }: { boot: Boot }) {
  const [form, setForm] = useState<DriverSettingsForm>(blankDriverSettings);
  const [profile, setProfile] = useState<TransportProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState('');
  const load = async () => {
    try {
      const response = await api<{ vendor_profiles: { profiles: TransportProfile[] } }>('vendor/profiles.php');
      const transport = response.vendor_profiles.profiles.find(item => item.type === 'transport') || null;
      setProfile(transport);
      const config = transport?.configuration || {};
      setForm({
        profile_name: String(config.profile_name || transport?.name || ''), vehicle_type: String(config.vehicle_type || 'bus'), total_seats: String(config.total_seats || ''), origin: String(config.origin || ''), destination: String(config.destination || ''), pickup_location: String(config.pickup_location || ''), departure_time: String(config.departure_time || '08:00'), fare_per_seat: String(config.fare_per_seat || ''), vehicle_image_url: String(config.vehicle_image_url || config.image_url || ''), driver_name: String(config.driver_name || boot.user?.name || ''), driver_phone: String(config.driver_phone || ''), description: String(config.description || ''),
      });
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Unable to load Driver Settings.'); }
    finally { setLoading(false); }
  };
  useEffect(() => { void load(); }, []);
  const update = (key: keyof DriverSettingsForm, value: string) => setForm(current => ({ ...current, [key]: value }));
  const save = async (event: React.FormEvent) => {
    event.preventDefault(); setSaving(true); setNotice('');
    try {
      const result = await api<{ result: { profile: TransportProfile } }>('vendor/profiles.php', 'POST', { action: 'save_transport_settings', ...form, total_seats: Number(form.total_seats), fare_per_seat: Number(form.fare_per_seat), schedule_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], operational_preferences: { station_mode: true } }, boot.csrf_token);
      setProfile(result.result.profile); setNotice(result.result.profile.active ? 'Driver settings saved. Your published service remains active.' : 'Private Driver draft saved. Publish only when you are ready.');
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Uthenga could not save these settings.'); }
    finally { setSaving(false); }
  };
  return <main className="driver-settings"><header><Link to="/driver">← Driver dashboard</Link><span>{profile?.active ? 'ACTIVE SERVICE' : 'PRIVATE DRAFT'}</span></header><section><p className="eyebrow">DRIVER OPERATIONS · SETTINGS</p><h1>Make the console yours.</h1><p className="settings-intro">These details are authoritative. Saving a draft never publishes a listing; changing an active service is blocked while a live transport session exists.</p>{loading ? <p className="muted">Loading verified service settings…</p> : <form className="settings-form" onSubmit={save}><fieldset><legend>Service identity</legend><label>Transport service name<input required value={form.profile_name} onChange={e => update('profile_name', e.target.value)} placeholder="Area 25 Express" /></label><label>Driver display name<input value={form.driver_name} onChange={e => update('driver_name', e.target.value)} placeholder="Patrick Banda" /></label><label>Driver phone<input value={form.driver_phone} onChange={e => update('driver_phone', e.target.value)} placeholder="Optional operations number" /></label><label>Vehicle image URL<input value={form.vehicle_image_url} onChange={e => update('vehicle_image_url', e.target.value)} placeholder="https://… or assets/images/..." /></label></fieldset><fieldset><legend>Vehicle and fare</legend><label>Vehicle type<select value={form.vehicle_type} onChange={e => update('vehicle_type', e.target.value)}>{['bus','coach','minibus','van','car','taxi'].map(type => <option key={type} value={type}>{type}</option>)}</select></label><label>Passenger capacity<input required type="number" min="1" max="500" value={form.total_seats} onChange={e => update('total_seats', e.target.value)} /></label><label>Fare per seat · MWK<input required type="number" min="1" step="100" value={form.fare_per_seat} onChange={e => update('fare_per_seat', e.target.value)} /></label></fieldset><fieldset><legend>Route and schedule</legend><label>From<input required value={form.origin} onChange={e => update('origin', e.target.value)} placeholder="Area 25" /></label><label>To<input required value={form.destination} onChange={e => update('destination', e.target.value)} placeholder="Lilongwe City Centre" /></label><label>Pickup point / terminal<input required value={form.pickup_location} onChange={e => update('pickup_location', e.target.value)} /></label><label>Usual departure time<input required type="time" value={form.departure_time} onChange={e => update('departure_time', e.target.value)} /></label><label className="wide">Passenger-facing description<textarea rows={3} value={form.description} onChange={e => update('description', e.target.value)} placeholder="Describe the service clearly for travellers." /></label></fieldset><div className="settings-save"><span>{profile?.active ? 'This updates the linked service only when there is no live run.' : 'This creates or updates a private draft only.'}</span><button className="button button--primary" disabled={saving}>{saving ? 'Saving verified settings…' : 'Save Driver Settings'}</button></div>{notice && <p className={notice.startsWith('Private') || notice.startsWith('Driver settings') ? 'settings-notice' : 'inline-error'}>{notice}</p>}</form>}</section></main>;
}

type PlannerFields = { destination: string; dates: string; travellers: string; purpose: string; budget: string };
function plannerDates(value: string): { start: string | null; end: string | null } | null {
  const dates = value.match(/\d{4}-\d{2}-\d{2}/g) || [];
  if (dates.length === 1) return { start: dates[0], end: dates[0] };
  if (dates.length === 2) return { start: dates[0], end: dates[1] };
  return null;
}
function plannerTravellers(value: string): number | null {
  const numbers = value.match(/\d+/g)?.map(Number) || [];
  const total = numbers.reduce((sum, item) => sum + item, 0); return total >= 1 && total <= 20 ? total : null;
}

function PlanningMap({ boot, destination, liveTracking, onToggleLiveTracking }: { boot: Boot; destination: string; liveTracking: boolean; onToggleLiveTracking: () => void }) {
  const [location, setLocation] = useState<Coordinates | null>(null);
  const [destinationPoint, setDestinationPoint] = useState<MapPoint | null>(null);
  const [route, setRoute] = useState<Route | null>(null);
  const [notice, setNotice] = useState('Use the location control to preview your route.');
  const watchId = useRef<number | null>(null);
  const resolveDestination = async () => {
    if (!destination.trim() || !boot.maps.enabled || !boot.maps.browser_key) return;
    try {
      const maps = await loadGoogleMaps(boot.maps.browser_key);
      const geocoder = new maps.Geocoder();
      const result = await geocoder.geocode({ address: `${destination}, Malawi` });
      const geometry = result?.results?.[0]?.geometry?.location;
      if (!geometry) { setDestinationPoint(null); setNotice('Destination map preview is unavailable until a recognised place is provided.'); return; }
      setDestinationPoint({ latitude: geometry.lat(), longitude: geometry.lng() });
      setNotice(liveTracking ? 'Live Travel is active for this foreground tab.' : 'Destination located. Add your current location to preview the journey.');
    } catch { setDestinationPoint(null); setNotice('Destination could not be located on the map yet.'); }
  };
  useEffect(() => { void resolveDestination(); }, [destination, boot.maps.browser_key, boot.maps.enabled]);
  useEffect(() => {
    if (!location || !destinationPoint || !boot.features.routing) { setRoute(null); return; }
    let cancelled = false;
    api<{ route: Route }>('routing/route.php', 'POST', { origin: location, destination: destinationPoint }, boot.csrf_token).then(result => { if (!cancelled) setRoute(result.route); }).catch(() => { if (!cancelled) setRoute(null); });
    return () => { cancelled = true; };
  }, [location, destinationPoint, boot.csrf_token, boot.features.routing]);
  const locate = async () => {
    try { const next = await foregroundLocation(); setLocation(next); setNotice(`Current location updated · accuracy ${Math.round(next.accuracy_m || 0)} m.`); intelligentFeedback(); }
    catch (error) { setNotice(error instanceof Error ? error.message : 'Current location is unavailable. Pin your exact point on the map instead.'); }
  };
  useEffect(() => {
    if (!liveTracking) { if (watchId.current !== null) navigator.geolocation.clearWatch(watchId.current); watchId.current = null; return; }
    if (!navigator.geolocation) { setNotice('Live Travel needs a supported browser location service.'); return; }
    setNotice('Live Travel is on. Sharing a foreground-only location while this tab stays open.');
    watchId.current = navigator.geolocation.watchPosition(position => {
      if (!Number.isFinite(position.coords.accuracy) || position.coords.accuracy > 100) { setNotice(`Live map update skipped: accuracy is about ${Math.round(position.coords.accuracy || 0)} m. Pin your location or improve GPS accuracy.`); return; }
      setLocation({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: position.coords.accuracy, permission: 'GRANTED', source: 'browser_geolocation' });
      setNotice(`Live location updated · accuracy ${Math.round(position.coords.accuracy)} m. This stops when you leave Live Travel or close this tab.`);
    }, () => setNotice('Live map update unavailable. Check location permission, or pin your exact point.'), { enableHighAccuracy: true, timeout: 20_000, maximumAge: 0 });
    return () => { if (watchId.current !== null) navigator.geolocation.clearWatch(watchId.current); watchId.current = null; };
  }, [liveTracking]);
  return <div className={'planning-map' + (liveTracking ? ' is-live' : '')}>
    <CustomerMap location={location} route={route} pickup={destinationPoint} mapsConfig={boot.maps} manualSelectionEnabled onLocate={locate} onManualLocation={next => { setLocation(next); setNotice('Pinned location set for this trip. It is used only in this browser session.'); }} />
    <div className="planning-map__status"><b>{liveTracking ? '● LIVE TRAVEL' : 'PLANNING MAP'}</b><span>{notice}</span>{route && <small>{Math.round(route.distance_m / 1000 * 10) / 10} km · about {Math.ceil(route.duration_seconds / 60)} min</small>}</div>
    <div className="planning-map__controls"><button onClick={() => void locate()}>⌖ Use current location</button><button className={liveTracking ? 'is-live' : ''} onClick={onToggleLiveTracking}>{liveTracking ? 'Stop Live Travel' : 'Activate Travel'}</button></div>
  </div>;
}

type PlannerTab = 'planner' | 'my-trips' | 'itineraries' | 'bookings' | 'messages' | 'invite' | 'saved-places' | 'documents' | 'preferences';
const PLANNER_NAV: [any, string, PlannerTab][] = [
  [Sparkles, 'Trip Planner', 'planner'], [CalendarDays, 'My Trips', 'my-trips'], [LayoutDashboard, 'Itineraries', 'itineraries'], [Ticket, 'Bookings', 'bookings'],
  [MessageSquare, 'Messages', 'messages'], [UserPlus, 'Invite People', 'invite'], [MapPin, 'Saved Places', 'saved-places'], [FileText, 'Documents', 'documents'], [Settings, 'Preferences', 'preferences'],
];

function TripPlanningWorkspace({ boot }: { boot: Boot }) {
  const initialDestination = new URLSearchParams(window.location.search).get('destination') || '';
  const initialPlanId = new URLSearchParams(window.location.search).get('plan') || '';
  const [tab, setTab] = useState<PlannerTab>(initialPlanId ? 'itineraries' : 'planner');
  const [step, setStep] = useState(0);
  const [fields, setFields] = useState<PlannerFields>({ destination: initialDestination, dates: '', travellers: '', purpose: '', budget: '' });
  const [tripDefaults, setTripDefaults] = useState<{ purpose?: string; travellers?: string; budget?: string } | null>(null);
  const [answer, setAnswer] = useState(initialDestination);
  const [plan, setPlan] = useState<any>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [liveTracking, setLiveTracking] = useState(false);
  const [recommendationCategory, setRecommendationCategory] = useState('all');
  const [showAllIssues, setShowAllIssues] = useState(false);
  const [askInput, setAskInput] = useState('');
  const [compareOptions, setCompareOptions] = useState<any[] | null>(null);
  const [comparing, setComparing] = useState(false);
  const [shareStatus, setShareStatus] = useState('');
  const navigate = useNavigate();

  // The open plan lives in the URL (?plan=...), not just React state, so
  // opening a trip and reloading the page — or sharing the link — doesn't
  // lose it. Loads once on mount if the URL already names a plan.
  const setOpenPlanId = (planId: string) => {
    const url = new URL(window.location.href);
    if (planId) url.searchParams.set('plan', planId); else url.searchParams.delete('plan');
    window.history.replaceState(null, '', url.toString());
  };
  const openTrip = async (planId: string) => {
    setBusy(true); setError('');
    try { const result = await api<{ plan: any }>(`plans/view.php?plan_id=${encodeURIComponent(planId)}`); setPlan(result.plan); setOpenPlanId(planId); setTab('itineraries'); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'That trip could not be opened.'); }
    finally { setBusy(false); }
  };
  useEffect(() => { if (initialPlanId) void openTrip(initialPlanId); }, []); // eslint-disable-line react-hooks/exhaustive-deps
  // The one real behavioural connection from Preferences to the AI planner:
  // a saved trip_defaults preference prefills a fresh intake. It does not
  // change recommendation ranking — the recommendation engine doesn't
  // consume preferences at all today — this only saves retyping.
  useEffect(() => {
    api<{ result: { preferences: { trip_defaults?: { purpose?: string; travellers?: number; budget?: number } } } }>('preferences/get.php')
      .then(response => {
        const defaults = response.result.preferences?.trip_defaults;
        if (!defaults) return;
        setTripDefaults({ purpose: defaults.purpose, travellers: defaults.travellers ? String(defaults.travellers) : undefined, budget: defaults.budget ? String(defaults.budget) : undefined });
      })
      .catch(() => undefined);
  }, []);
  const startNewTrip = () => {
    setPlan(null); setStep(0); setAnswer('');
    setFields({ destination: '', dates: '', travellers: tripDefaults?.travellers || '', purpose: tripDefaults?.purpose || '', budget: tripDefaults?.budget || '' });
    setOpenPlanId(''); setTab('planner');
  };
  const questions = [
    ['Where are you planning to go?', 'e.g. Mzuzu, Mangochi, Blantyre…'],
    ['When are you planning to travel?', 'Use YYYY-MM-DD or YYYY-MM-DD to YYYY-MM-DD'],
    ['How many people are travelling?', 'e.g. 4 adults and 2 children'],
    ['What is the purpose of this trip?', 'Vacation, business, family, church, education…'],
    ['What is your approximate total budget?', 'e.g. 1200000'],
  ];
  const current = questions[step];
  const store = (value: string) => setFields(previous => ({ ...previous, [(['destination', 'dates', 'travellers', 'purpose', 'budget'][step] as keyof PlannerFields)]: value }));
  const advance = () => {
    const value = answer.trim();
    if (!value) return setError('Please answer this one question to continue.');
    if (step === 1 && !plannerDates(value)) return setError('Use a date such as 2026-08-15, or a range such as 2026-08-15 to 2026-08-20.');
    if (step === 2 && !plannerTravellers(value)) return setError('Tell me how many people are travelling (between 1 and 20).');
    if (step === 4 && (!Number.isFinite(Number(value)) || Number(value) < 0)) return setError('Enter a whole budget amount in MWK.');
    intelligentFeedback(); setError(''); store(value);
    if (step < questions.length - 1) { setStep(currentStep => currentStep + 1); setAnswer(''); }
    else void createPlan({ ...fields, budget: value });
  };
  const createPlan = async (complete: PlannerFields) => {
    const dates = plannerDates(complete.dates); const travellers = plannerTravellers(complete.travellers);
    if (!dates || !travellers) return;
    setBusy(true); setError('');
    try {
      const result = await api<{ plan: any }>('plans/create.php', 'POST', { title: `${complete.purpose || 'Travel'} trip to ${complete.destination}`, destination: complete.destination, origin: null, start_date: dates.start, end_date: dates.end, travellers, budget: Number(complete.budget), currency: 'MWK', preferences: [complete.purpose], travel_mode: 'any', max_daily_activities: 3, limit: 16 }, boot.csrf_token);
      setPlan(result.plan); setOpenPlanId(result.plan.plan_id); intelligentFeedback('confirm');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Uthenga could not build this plan.'); }
    finally { setBusy(false); }
  };
  const planAction = async (path: string) => {
    if (!plan?.plan_id) return;
    setBusy(true); setError('');
    try { const result = await api<{ plan: any }>(path, 'POST', { plan_id: plan.plan_id }, boot.csrf_token); setPlan(result.plan); intelligentFeedback(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'That plan action could not be completed.'); }
    finally { setBusy(false); }
  };
  const updateActivity = async (operation: 'remove' | 'reorder', serviceId: string, targetDate?: string) => {
    if (!plan?.plan_id) return;
    setBusy(true); setError('');
    try {
      const result = await api<{ plan: any }>('plans/update.php', 'POST', { plan_id: plan.plan_id, operation, service_id: serviceId, ...(targetDate ? { target_date: targetDate } : {}) }, boot.csrf_token);
      setPlan(result.plan); intelligentFeedback();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'That change could not be made.'); }
    finally { setBusy(false); }
  };
  const askAssistant = () => {
    const question = askInput.trim();
    if (!question) return;
    navigate(`/assistant?destination=${encodeURIComponent(fields.destination || plan?.trip_summary?.destination || '')}&message=${encodeURIComponent(question)}`);
  };
  const sharePlan = async () => {
    if (!plan) return;
    const text = `${plan.title} — ${plan.trip_summary?.destination}, ${plan.trip_summary?.start_date} to ${plan.trip_summary?.end_date}.`;
    try {
      if (navigator.share) await navigator.share({ title: plan.title, text });
      else { await navigator.clipboard.writeText(text); setShareStatus('Plan details copied to clipboard.'); window.setTimeout(() => setShareStatus(''), 4000); }
    } catch { /* user dismissed the share sheet; not an error */ }
  };
  // Real alternative plans, generated the same way the current one was — not
  // a fabricated "option B/C". Varying max_daily_activities is the one
  // real lever the engine exposes for a lighter vs. busier version.
  const compareOptionsNow = async () => {
    if (!plan) return;
    setComparing(true); setError('');
    try {
      const variants = await Promise.all([2, 5].map(maxDaily =>
        api<{ plan: any }>('plans/create.php', 'POST', { ...createInputFrom(plan), max_daily_activities: maxDaily }, boot.csrf_token).then(r => r.plan)
      ));
      setCompareOptions([plan, ...variants]);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not generate alternatives to compare.'); }
    finally { setComparing(false); }
  };
  const createInputFrom = (source: any) => ({
    title: source.title, destination: source.trip_summary?.destination, origin: source.trip_summary?.origin || null,
    start_date: source.trip_summary?.start_date, end_date: source.trip_summary?.end_date, travellers: source.trip_summary?.travellers,
    budget: source.trip_summary?.budget, currency: source.trip_summary?.currency || 'MWK', preferences: source.request?.preferences || [],
    travel_mode: source.request?.travel_mode || 'any', max_daily_activities: 3, limit: 16,
  });
  const choosePlanOption = (chosen: any) => { setPlan(chosen); setOpenPlanId(chosen.plan_id); setCompareOptions(null); intelligentFeedback('confirm'); };
  const dates = plannerDates(fields.dates); const travellerCount = plannerTravellers(fields.travellers);
  const issues = plan?.conflict_analysis?.issues || []; const blocking = plan?.conflict_analysis?.summary?.blocking || 0; const warnings = plan?.conflict_analysis?.summary?.warnings || 0;
  const readiness = plan ? Math.max(0, 100 - blocking * 35 - warnings * 8 - (plan.activities?.length ? 0 : 22)) : 0;
  const groups = (plan?.activities || []).reduce((all: Record<string, any[]>, activity: any) => { (all[activity.category] ||= []).push(activity); return all; }, {});
  const itinerary = plan?.activities || [];
  const budgetLines = plan?.budget_analysis?.lines || [];
  return <main className="planner-reference">
    <aside className="planner-reference__rail">
      <Link to="/" className="planner-reference__brand"><UthengaLogoImg size="sm" /><span><small style={{ display: 'block', fontSize: '0.65rem', color: '#5beaab', marginTop: '2px', fontWeight: 600 }}>Trip Planning Assistant</small></span></Link>
      <section className="planner-reference__person"><div>{boot.user?.name?.slice(0, 1) || 'U'}<i /></div><p><b>{boot.user?.name || 'Traveller'}</b><small>Traveller</small><em>● Online</em></p></section>
      <nav>{PLANNER_NAV.map(([Icon, label, key]) => <button className={tab === key ? 'is-active' : ''} key={label} onClick={() => setTab(key)}><Icon /><span>{label}</span></button>)}</nav>
      <section className="planner-reference__help"><b>Need help planning?</b><p>Our travel experts are ready to help you 24/7.</p><Link to="/assistant">Chat with Expert <Headphones /></Link></section>
    </aside>
    {tab === 'my-trips' && <MyTripsWorkspace boot={boot} onOpenTrip={openTrip} onNewTrip={startNewTrip} />}
    {tab === 'itineraries' && <ItinerariesWorkspace plan={plan} busy={busy} error={error} onRemove={serviceId => void updateActivity('remove', serviceId)} onReorder={(serviceId, date) => void updateActivity('reorder', serviceId, date)} onValidate={() => void planAction('plans/validate.php')} onApprove={() => void planAction('plans/approve.php')} onOpenMyTrips={() => setTab('my-trips')} />}
    {tab === 'bookings' && <BookingsWorkspace boot={boot} />}
    {tab === 'invite' && <InvitePeopleWorkspace boot={boot} plan={plan} onOpenMyTrips={() => setTab('my-trips')} />}
    {tab === 'messages' && <TripMessagesWorkspace boot={boot} plan={plan} onOpenMyTrips={() => setTab('my-trips')} />}
    {tab === 'saved-places' && <SavedPlacesWorkspace boot={boot} />}
    {tab === 'documents' && <DocumentsWorkspace boot={boot} />}
    {tab === 'preferences' && <PreferencesWorkspace boot={boot} onSaved={preferences => { const defaults = preferences?.trip_defaults; if (defaults) setTripDefaults({ purpose: defaults.purpose, travellers: defaults.travellers ? String(defaults.travellers) : undefined, budget: defaults.budget ? String(defaults.budget) : undefined }); }} />}
    {tab === 'planner' && <section className="planner-reference__body">
      <header className="planner-reference__top"><div><h1>Hello {boot.user?.name?.split(' ')[0] || 'Traveller'}! <span>👋</span></h1><p>I’ll help you plan the perfect trip. Let’s start.</p></div><div><a className="planner-reference__dashboard-link" href={boot.legacy_fallbacks?.main_dashboard || '/uthenga/dashboard.php'}><LayoutDashboard size={15} /> Dashboard</a><span>🌤 &nbsp;26°C<small>Lilongwe, Malawi</small></span><Bell /><button onClick={() => setTab('invite')}><UserPlus /> Invite</button></div></header>
      <div className="planner-reference__grid">
        <section className="planner-reference__main">
          <article className="planner-reference__prompt"><div className="planner-reference__star"><Sparkles /></div><div><h2>{plan ? 'Your verified trip plan is ready.' : `${step > 0 ? 'Great. ' : ''}${current?.[0] || 'What would you like to adjust?'}`}</h2>{!plan && current && <>{step === 3 ? <div className="planner-reference__purpose">{['Vacation','Business','Family','Church','Education','Other'].map(option => <button key={option} className={answer === option ? 'is-selected' : ''} onClick={() => { setAnswer(option); intelligentFeedback(); }}>{option}</button>)}</div> : <input autoFocus value={answer} placeholder="|" onChange={event => setAnswer(event.target.value)} onKeyDown={event => event.key === 'Enter' && advance()} />}{step === 0 && <><p>You can type naturally, for example:</p><div className="planner-reference__examples"><button onClick={() => setAnswer('I want to visit Mzuzu')}>I want to visit Mzuzu</button><button onClick={() => setAnswer('A business trip to Blantyre')}>A business trip to Blantyre</button><button onClick={() => setAnswer('Weekend in Cape Maclear')}>Weekend in Cape Maclear</button></div></>}<footer><Paperclip /><button disabled={busy} onClick={advance}>{busy ? 'Building…' : step === questions.length - 1 ? 'Build trip' : 'Continue'} <Send /></button></footer></>}{plan && <><p className="planner-reference__consultant"><Bot /> {plan.explanation?.summary || 'Your itinerary is composed from validated Uthenga recommendations.'}</p><footer><button onClick={() => void planAction('plans/validate.php')} disabled={busy}>Validate plan <Check /></button></footer></>}{error && <small className="planner-reference__error">{error}</small>}</div></article>
          <article className="planner-reference__itinerary"><header><b>YOUR ITINERARY</b><span className={plan ? 'is-clickable' : ''} onClick={() => plan && setTab('itineraries')}>View Full Itinerary</span></header>{itinerary.length ? <div className="planner-reference__timeline">{itinerary.slice(0,6).map((activity: any) => <section key={activity.activity_id}><time>{activity.start_at.slice(0,10)}</time><div className="planner-reference__node"/><p><b>{activity.title}</b><small>{activity.start_at.slice(11,16)} · {activity.category}</small></p><span>{activity.price?.amount ? `MWK ${Number(activity.price.amount).toLocaleString()}` : 'Proposed'}</span></section>)}</div> : <div className="planner-reference__timeline is-empty"><p>Your itinerary appears here after Uthenga has your dates, travellers, purpose, and budget.</p></div>}</article>
          <article className="planner-reference__recommendations">
            <header><b>AI RECOMMENDATIONS</b><span className={plan ? 'is-clickable' : ''} onClick={() => plan && setTab('itineraries')}>View All</span></header>
            <nav>{[['transport','Transport',BusFront],['accommodation','Accommodation',Hotel],['activities','Activities',Sparkles],['all','All',Layers]].map(([key,label,Icon]: any) => <button key={key} className={recommendationCategory === key ? 'is-active' : ''} onClick={() => setRecommendationCategory(key)}><Icon /> {label}</button>)}</nav>
            {(() => {
              const filtered = itinerary.filter((activity: any) => recommendationCategory === 'all' ? true : recommendationCategory === 'activities' ? !['transport', 'accommodation'].includes(activity.category) : activity.category === recommendationCategory);
              return filtered.length ? <div className="planner-reference__recommendation-row">{filtered.slice(0,3).map((activity: any, index: number) => <article key={activity.activity_id}><div className={'planner-reference__recommendation-icon is-' + activity.category}>{activity.category === 'transport' ? <BusFront /> : activity.category === 'accommodation' ? <Hotel /> : <Sparkles />}<small>{index === 0 ? 'RECOMMENDED' : 'VERIFIED'}</small></div><b>{activity.title}</b><span>{activity.location?.display_name || activity.location?.city || activity.category}</span><em>{activity.price?.amount ? `MWK ${Number(activity.price.amount).toLocaleString()}` : 'Price pending'}</em></article>)}<aside><b>Why these options</b><p><Check /> Current marketplace availability was checked.</p><p><Check /> Their dates and prices were evaluated.</p><p><Check /> They fit the itinerary timeline.</p></aside></div> : <p className="planner-reference__empty">{itinerary.length ? 'No recommendations in this category yet.' : 'Recommendations appear once the server has built a verified plan.'}</p>;
            })()}
          </article>
          <div className="planner-reference__ask"><input value={askInput} onChange={event => setAskInput(event.target.value)} onKeyDown={event => event.key === 'Enter' && askAssistant()} placeholder="Ask anything about your trip…" /><Mic /><button onClick={askAssistant}><Send /></button></div>
        </section>
        <aside className="planner-reference__side">
          <article className="planner-reference__overview"><section><header><b>TRIP OVERVIEW</b><span>{liveTracking ? 'Live Travel' : plan?.lifecycle || 'Draft'}</span></header>{[['Destination',fields.destination],['Dates',dates ? dates.start === dates.end ? dates.start : `${dates.start} – ${dates.end}` : 'Not set'],['Travellers',travellerCount ? `${travellerCount} travellers` : 'Not set'],['Purpose',fields.purpose || 'Not set'],['Budget',fields.budget ? `MWK ${Number(fields.budget).toLocaleString()}` : 'Not set']].map(([label,value]) => <p key={label}><small>{label}</small><b>{value}</b><i>⌕</i></p>)}<footer><span>Plan Progress</span><i><b style={{width:`${plan ? readiness : Math.min(65, step * 15)}%`}} /></i><em>{plan ? readiness : Math.min(65, step * 15)}%</em></footer></section><PlanningMap boot={boot} destination={fields.destination} liveTracking={liveTracking} onToggleLiveTracking={() => { if (!plan && !liveTracking) { setError('Build and review a trip plan before activating Live Travel.'); return; } setLiveTracking(active => !active); intelligentFeedback(); }} /></article>
          <article className="planner-reference__budget"><header><b>BUDGET ESTIMATE</b><span className={plan ? 'is-clickable' : ''} onClick={() => plan && setTab('itineraries')}>View Breakdown</span></header><div className="planner-reference__donut"><b>MWK {Number(plan?.budget_analysis?.estimated_total || 0).toLocaleString()}</b><i/></div>{budgetLines.length ? budgetLines.map((line:any,index:number) => <p key={`${line.category}-${index}`}><i/><span>{line.category}</span><b>MWK {Number(line.amount || 0).toLocaleString()}</b></p>) : <small>Budget analysis appears with the verified plan.</small>}<footer>Remaining Budget <b>{plan?.budget_analysis?.remaining_budget === null ? '—' : `MWK ${Number(plan?.budget_analysis?.remaining_budget || 0).toLocaleString()}`}</b></footer></article>
          <article className="planner-reference__insights"><header><b>{liveTracking ? 'LIVE TRAVEL AGENT' : 'AI INSIGHTS'}</b><span className={!liveTracking && issues.length > 2 ? 'is-clickable' : ''} onClick={() => !liveTracking && issues.length > 2 && setShowAllIssues(current => !current)}>{liveTracking ? 'FOREGROUND ONLY' : issues.length > 2 ? (showAllIssues ? 'Show Less' : 'View All') : ''}</span></header><p><i><Bot /></i>{liveTracking ? 'Live Travel is active. I am keeping this open tab’s foreground map current and will stop when you deactivate travel or close the tab.' : plan ? (plan.explanation?.facts?.budget_status === 'OVER_BUDGET' ? 'This proposal is above budget. Compare lower-cost alternatives before approval.' : 'Validated marketplace evidence has been composed into this itinerary.') : 'I will explain choices after I have enough trip context.'}</p>{(showAllIssues ? issues : issues.slice(0,2)).map((issue:any,index:number) => <p key={`${issue.code}-${index}`}><i>!</i>{issue.message}</p>)}</article>
        </aside>
      </div>
      <footer className="planner-reference__actions">
        <button disabled={!plan} onClick={() => setTab('itineraries')}><CalendarDays /> Full Itinerary</button>
        <button disabled={!plan || comparing} onClick={() => void compareOptionsNow()}><Layers /> {comparing ? 'Comparing…' : 'Compare Options'}</button>
        <button disabled={!plan} onClick={() => void sharePlan()}><Share2 /> Share Plan</button>
        {shareStatus && <small className="planner-reference__share-status">{shareStatus}</small>}
        <button className={liveTracking ? 'is-live' : ''} disabled={!plan && !liveTracking} onClick={() => { if (!plan && !liveTracking) return; setLiveTracking(active => !active); intelligentFeedback(); }}><Navigation /> {liveTracking ? 'Stop Live Travel' : 'Activate Travel'}</button>
        <button disabled={!plan || busy || blocking > 0} onClick={() => void planAction('plans/approve.php')}><FileText /> Approve Plan</button>
      </footer>
    </section>}
    {compareOptions && <CompareOptionsModal options={compareOptions} onChoose={choosePlanOption} onClose={() => setCompareOptions(null)} />}
  </main>;
}

const COMPARE_OPTION_LABELS = ['Current Plan', 'Lighter Pace', 'Packed Pace'];
function CompareOptionsModal({ options, onChoose, onClose }: { options: any[]; onChoose: (plan: any) => void; onClose: () => void }) {
  return <div className="doc-modal-backdrop" onMouseDown={onClose}>
    <div className="compare-modal" onMouseDown={event => event.stopPropagation()}>
      <header><h3>Compare Options</h3><button type="button" onClick={onClose}>×</button></header>
      <div className="compare-modal__body">
        {options.map((option, index) => <article key={option.plan_id}>
          <b>{COMPARE_OPTION_LABELS[index] || `Option ${index + 1}`}</b>
          <span>{option.trip_summary?.destination}</span>
          <p><small>Activities</small><em>{(option.activities || []).length}</em></p>
          <p><small>Estimated Budget</small><em>MWK {Number(option.budget_analysis?.estimated_total || 0).toLocaleString()}</em></p>
          <p><small>Status</small><em>{option.lifecycle}</em></p>
          <button type="button" disabled={option.plan_id === options[0].plan_id} onClick={() => onChoose(option)}>{option.plan_id === options[0].plan_id ? 'Currently Active' : 'Choose This'}</button>
        </article>)}
      </div>
    </div>
  </div>;
}

type MyTripSummary = { plan_id: string; title: string; destination: string; duration_days: number; travel_date: string | null; budget: number | null; travellers: number; lifecycle: string; status: string; updated_at: string; role: 'owner' | 'viewer' | 'editor' };
const TRIP_STATUS_LABELS: Record<string, string> = { draft: 'Draft', upcoming: 'Upcoming', active: 'Active', completed: 'Completed', archived: 'Archived', approved: 'Approved' };
const TRIP_FILTERS = ['all', 'draft', 'upcoming', 'active', 'completed', 'archived'];

// The customer's journeys as complete entities — sourced from the same
// trip_itineraries rows the Trip Planner already writes. Status is exactly
// what plans/list.php derives server-side from real lifecycle + dates;
// nothing here is invented client-side.
function MyTripsWorkspace({ boot, onOpenTrip, onNewTrip }: { boot: Boot; onOpenTrip: (planId: string) => void; onNewTrip: () => void }) {
  const [trips, setTrips] = useState<MyTripSummary[] | null>(null);
  const [filter, setFilter] = useState('all');
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ result: { trips: MyTripSummary[] } }>('plans/list.php')
      .then(response => { setTrips(response.result.trips); setError(''); })
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load your trips.'));
  }, []);

  const filtered = trips ? (filter === 'all' ? trips : trips.filter(t => t.status === filter)) : [];

  return <section className="planner-reference__body my-trips">
    <header className="planner-reference__top"><div><h1>My Trips</h1><p>All your planned, active and completed journeys in one place.</p></div><button className="my-trips__new" onClick={onNewTrip}>+ New Trip</button></header>
    <nav className="planner-tabstrip my-trips__filters">{TRIP_FILTERS.map(f => <button key={f} className={filter === f ? 'is-active' : ''} onClick={() => setFilter(f)}>{f === 'all' ? 'All' : TRIP_STATUS_LABELS[f] || f}</button>)}</nav>
    {error && <p className="planner-reference__error">{error}</p>}
    {!trips && !error && <p className="my-trips__loading">Loading your trips…</p>}
    {trips && trips.length === 0 && <div className="my-trips__empty"><p>No trips yet.</p><p>Start planning your first journey with the Uthenga Trip Planning Assistant.</p><button onClick={onNewTrip}>Plan a Trip</button></div>}
    {trips && trips.length > 0 && filtered.length === 0 && <div className="my-trips__empty"><p>No trips match this filter.</p></div>}
    <div className="my-trips__grid">
      {filtered.map(trip => <article key={trip.plan_id} className="my-trips__card">
        <header><b>{trip.title}</b><span className={`my-trips__status is-${trip.status}`}>{TRIP_STATUS_LABELS[trip.status] || trip.status}</span></header>
        {trip.role !== 'owner' && <span className="my-trips__shared"><Users size={12} /> Shared with you · {trip.role}</span>}
        <p className="my-trips__destination">{trip.destination}</p>
        <p className="my-trips__dates">{trip.travel_date ? `${trip.travel_date} · ${trip.duration_days} day${trip.duration_days === 1 ? '' : 's'}` : 'Dates not yet set'}</p>
        <p className="my-trips__meta">{trip.travellers} traveller{trip.travellers === 1 ? '' : 's'}{trip.budget ? ` · MWK ${trip.budget.toLocaleString()}` : ''}</p>
        <button className="my-trips__open" onClick={() => onOpenTrip(trip.plan_id)}>Open Trip</button>
      </article>)}
    </div>
  </section>;
}

// The day-by-day execution plan for whichever trip is currently open — built
// directly on plans/view.php's real daily_itinerary grouping and
// plans/update.php's already-working remove/reorder operations (both
// existed in the backend before this tab did). Replacing an activity isn't
// offered here yet: doing that honestly needs a way to show the plan's
// actual current candidate set, which no read endpoint exposes today.
function ItinerariesWorkspace({ plan, busy, error, onRemove, onReorder, onValidate, onApprove, onOpenMyTrips }: {
  plan: any; busy: boolean; error: string; onRemove: (serviceId: string) => void; onReorder: (serviceId: string, date: string) => void;
  onValidate: () => void; onApprove: () => void; onOpenMyTrips: () => void;
}) {
  const [reorderTarget, setReorderTarget] = useState<Record<string, string>>({});
  if (!plan) return <section className="planner-reference__body itineraries"><div className="my-trips__empty"><p>No trip is open.</p><p>Choose one from My Trips, or start planning a new one.</p><button onClick={onOpenMyTrips}>Go to My Trips</button></div></section>;

  const days: { date: string; activities: any[] }[] = plan.daily_itinerary || [];
  const issues = plan.conflict_analysis?.issues || [];
  const startDate = plan.trip_summary?.start_date || undefined;
  const endDate = plan.trip_summary?.end_date || undefined;

  return <section className="planner-reference__body itineraries">
    <header className="planner-reference__top"><div><h1>{plan.title}</h1><p>{plan.trip_summary?.start_date && plan.trip_summary?.end_date ? `${plan.trip_summary.start_date} – ${plan.trip_summary.end_date}` : plan.trip_summary?.destination}</p></div><span className={`my-trips__status is-${plan.lifecycle?.toLowerCase()}`}>{plan.lifecycle}</span></header>
    {error && <p className="planner-reference__error">{error}</p>}
    {issues.length > 0 && <div className="itineraries__conflicts"><b>{issues.filter((i: any) => i.severity === 'blocking').length > 0 ? '⚠ Conflicts to resolve' : 'Notes'}</b>{issues.map((issue: any, index: number) => <p key={index} className={issue.severity === 'blocking' ? 'is-blocking' : ''}>{issue.message}</p>)}</div>}
    <div className="itineraries__actions"><button disabled={busy} onClick={onValidate}>Validate plan</button><button disabled={busy || plan.lifecycle !== 'VALIDATED'} onClick={onApprove}>Approve Plan</button></div>
    {days.length === 0 && <p className="my-trips__loading">This plan has no activities yet.</p>}
    {days.map(day => <article key={day.date} className="itineraries__day">
      <h3>{day.date}</h3>
      {day.activities.map((activity: any) => <div key={activity.activity_id} className="itineraries__item">
        <time>{activity.start_at?.slice(11, 16)}</time>
        <div className="itineraries__item-body">
          <b>{activity.title}</b>
          <small>{activity.category}{activity.location?.display_name || activity.location?.city ? ` · ${activity.location.display_name || activity.location.city}` : ''}</small>
          {activity.price?.amount ? <span>MWK {Number(activity.price.amount).toLocaleString()}</span> : <span className="is-muted">Proposed</span>}
        </div>
        <div className="itineraries__item-actions">
          <input type="date" min={startDate} max={endDate} value={reorderTarget[activity.service_id] || ''} onChange={event => setReorderTarget(current => ({ ...current, [activity.service_id]: event.target.value }))} />
          <button disabled={busy || !reorderTarget[activity.service_id]} onClick={() => onReorder(activity.service_id, reorderTarget[activity.service_id])}>Move</button>
          <button disabled={busy} className="is-danger" onClick={() => onRemove(activity.service_id)}>Remove</button>
        </div>
      </div>)}
    </article>)}
  </section>;
}

type CustomerBookingItem = {
  id: string; source: 'marketplace' | 'quick_taxi'; category: string; title: string; image: string | null; date: string | null; booked_at: string | null;
  currency: string; amount: number; booking_status: string; payment_status: string; confirmed_at: string | null; cancelled_at: string | null; completed_at: string | null;
};

// The customer's real reservations and payments — marketplace bookings
// (accommodation/event/tour/transport) merged with Quick Taxi payment
// history, exactly as bookings/list.php returns them. Cancellation reuses
// the already-working legacy cancel_booking action directly (same PHP
// session + CSRF token every TIE endpoint already uses) rather than
// reinventing a second booking-mutation path.
function BookingsWorkspace({ boot }: { boot: Boot }) {
  const [bookings, setBookings] = useState<CustomerBookingItem[] | null>(null);
  const [category, setCategory] = useState('all');
  const [status, setStatus] = useState('all');
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState('');

  const load = () => api<{ result: { bookings: CustomerBookingItem[] } }>('bookings/list.php')
    .then(response => { setBookings(response.result.bookings); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load your bookings.'));
  useEffect(() => { void load(); }, []);

  const categories = useMemo(() => ['all', ...Array.from(new Set((bookings || []).map(b => b.category)))], [bookings]);
  const statuses = useMemo(() => ['all', ...Array.from(new Set((bookings || []).map(b => b.booking_status)))], [bookings]);
  const filtered = (bookings || []).filter(b => (category === 'all' || b.category === category) && (status === 'all' || b.booking_status === status));

  const cancel = async (id: string) => {
    if (!window.confirm('Cancel this booking? This cannot be undone.')) return;
    setBusyId(id); setError('');
    try {
      const body = new URLSearchParams({ action: 'cancel_booking', booking_id: id, csrf_token: boot.csrf_token || '' });
      const response = await fetch(`${runtimeBase}request_api.php`, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
      const data = await response.json().catch(() => ({}));
      if (!data.success) throw new Error(data.message || 'Could not cancel this booking.');
      await load();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not cancel this booking.'); }
    finally { setBusyId(''); }
  };

  return <section className="planner-reference__body bookings">
    <header className="planner-reference__top"><div><h1>Bookings</h1><p>Manage all your Uthenga reservations and payments.</p></div></header>
    <nav className="planner-tabstrip bookings__filters">{categories.map(c => <button key={c} className={category === c ? 'is-active' : ''} onClick={() => setCategory(c)}>{c === 'all' ? 'All' : c === 'quick_taxi' ? 'Quick Taxi' : c.charAt(0).toUpperCase() + c.slice(1)}</button>)}</nav>
    <nav className="planner-tabstrip bookings__filters">{statuses.map(s => <button key={s} className={status === s ? 'is-active' : ''} onClick={() => setStatus(s)}>{s === 'all' ? 'All Statuses' : s.replaceAll('_', ' ')}</button>)}</nav>
    {error && <p className="planner-reference__error">{error}</p>}
    {!bookings && !error && <p className="my-trips__loading">Loading your bookings…</p>}
    {bookings && bookings.length === 0 && <div className="my-trips__empty"><p>No bookings yet.</p></div>}
    {bookings && bookings.length > 0 && filtered.length === 0 && <div className="my-trips__empty"><p>No bookings match these filters.</p></div>}
    <div className="bookings__grid">
      {filtered.map(b => <article key={b.id} className="bookings__card">
        <header><b>{b.category === 'quick_taxi' ? 'TRANSPORT' : b.category.toUpperCase()}</b>{b.source === 'quick_taxi' && <small>Quick Taxi</small>}</header>
        <h3>{b.title}</h3>
        {b.date && <p className="bookings__date">{b.date}</p>}
        <dl className="bookings__status-row">
          <div><dt>Booking</dt><dd className={`my-trips__status is-${b.booking_status}`}>{b.booking_status.replaceAll('_', ' ')}</dd></div>
          <div><dt>Payment</dt><dd className={`my-trips__status is-${b.payment_status}`}>{b.payment_status.replaceAll('_', ' ')}</dd></div>
        </dl>
        <p className="bookings__amount">{b.currency} {b.amount.toLocaleString()}</p>
        {b.source === 'marketplace' && !['cancelled', 'refunded'].includes(b.booking_status) && <footer><button disabled={busyId === b.id} onClick={() => void cancel(b.id)}>{busyId === b.id ? 'Cancelling…' : 'Cancel Booking'}</button></footer>}
      </article>)}
    </div>
  </section>;
}

type TripMember = { user_id: string; name: string; email: string | null; role: 'owner' | 'viewer' | 'editor' };

// Real multi-user trip access — an invite resolves immediately against a
// real Uthenga account found by email (no account, no invite: an honest
// failure rather than pretending an email was sent to a non-user). Editors
// can edit the itinerary via plans/update.php; viewers are read-only —
// enforced server-side in Planning.php, not just hidden in this UI.
function InvitePeopleWorkspace({ boot, plan, onOpenMyTrips }: { boot: Boot; plan: any; onOpenMyTrips: () => void }) {
  const [members, setMembers] = useState<TripMember[] | null>(null);
  const [viewerRole, setViewerRole] = useState<'owner' | 'viewer' | 'editor'>('viewer');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<'viewer' | 'editor'>('viewer');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = () => {
    if (!plan?.plan_id) return;
    void api<{ result: { viewer_role: 'owner' | 'viewer' | 'editor'; members: TripMember[] } }>(`trip-collaboration/members.php?plan_id=${encodeURIComponent(plan.plan_id)}`)
      .then(response => { setMembers(response.result.members); setViewerRole(response.result.viewer_role); setError(''); })
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load trip members.'));
  };
  useEffect(() => { void load(); }, [plan?.plan_id]); // eslint-disable-line react-hooks/exhaustive-deps

  const invite = async (event: React.FormEvent) => {
    event.preventDefault(); if (!plan?.plan_id || !email.trim()) return;
    setBusy(true); setError('');
    try {
      const response = await api<{ result: { members: TripMember[] } }>('trip-collaboration/action.php', 'POST', { action: 'invite', plan_id: plan.plan_id, email: email.trim(), role }, boot.csrf_token);
      setMembers(response.result.members); setEmail('');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not send that invite.'); }
    finally { setBusy(false); }
  };
  const changeRole = async (memberUserId: string, nextRole: 'viewer' | 'editor') => {
    if (!plan?.plan_id) return; setBusy(true); setError('');
    try { const response = await api<{ result: { members: TripMember[] } }>('trip-collaboration/action.php', 'POST', { action: 'change_role', plan_id: plan.plan_id, member_user_id: memberUserId, role: nextRole }, boot.csrf_token); setMembers(response.result.members); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not change that role.'); }
    finally { setBusy(false); }
  };
  const revoke = async (memberUserId: string) => {
    if (!plan?.plan_id || !window.confirm('Remove this person from the trip?')) return;
    setBusy(true); setError('');
    try { const response = await api<{ result: { members: TripMember[] } }>('trip-collaboration/action.php', 'POST', { action: 'revoke', plan_id: plan.plan_id, member_user_id: memberUserId }, boot.csrf_token); setMembers(response.result.members); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not remove that person.'); }
    finally { setBusy(false); }
  };

  if (!plan) return <section className="planner-reference__body invite-people"><div className="my-trips__empty"><p>No trip is open.</p><p>Open a trip from My Trips to manage who's invited.</p><button onClick={onOpenMyTrips}>Go to My Trips</button></div></section>;

  return <section className="planner-reference__body invite-people">
    <header className="planner-reference__top"><div><h1>Invite People</h1><p>Bring your travel companions into "{plan.title}".</p></div></header>
    {error && <p className="planner-reference__error">{error}</p>}
    {viewerRole === 'owner' && <form className="invite-people__form" onSubmit={invite}>
      <input type="email" required placeholder="Email of an existing Uthenga account" value={email} onChange={event => setEmail(event.target.value)} />
      <select value={role} onChange={event => setRole(event.target.value as 'viewer' | 'editor')}><option value="viewer">Viewer</option><option value="editor">Editor</option></select>
      <button disabled={busy} type="submit">{busy ? 'Inviting…' : 'Invite'}</button>
    </form>}
    <h4 className="invite-people__section-title">Current Members</h4>
    {!members && !error && <p className="my-trips__loading">Loading members…</p>}
    <div className="invite-people__list">
      {members?.map(m => <div key={m.user_id} className="invite-people__row">
        <div><b>{m.name}</b>{m.email && <small>{m.email}</small>}</div>
        {viewerRole === 'owner' && m.role !== 'owner' ? <div className="invite-people__row-actions">
          <select value={m.role} onChange={event => void changeRole(m.user_id, event.target.value as 'viewer' | 'editor')} disabled={busy}><option value="viewer">Viewer</option><option value="editor">Editor</option></select>
          <button disabled={busy} onClick={() => void revoke(m.user_id)}>Remove</button>
        </div> : <span className={`my-trips__status is-${m.role}`}>{m.role}</span>}
      </div>)}
    </div>
  </section>;
}

type TripMessageItem = { id: number; sender_id: string; sender_name: string; body: string; created_at: string };

// A real shared group thread for whichever trip is open — the first
// genuinely multi-participant conversation in this codebase (every other
// messaging table here is a strict 1:1 pair). The "AI Trip Assistant" the
// spec wants inside Messages is a link into the existing /assistant page
// (already real, already evidence-only) rather than a second chat engine.
function TripMessagesWorkspace({ boot, plan, onOpenMyTrips }: { boot: Boot; plan: any; onOpenMyTrips: () => void }) {
  const [messages, setMessages] = useState<TripMessageItem[] | null>(null);
  const [text, setText] = useState('');
  const [error, setError] = useState('');
  const historyRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!plan?.plan_id) return;
    const load = () => api<{ result: { messages: TripMessageItem[] } }>(`trip-messages/list.php?plan_id=${encodeURIComponent(plan.plan_id)}`)
      .then(response => { setMessages(response.result.messages); setError(''); })
      .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load messages.'));
    void load(); const interval = window.setInterval(load, 4_000); return () => window.clearInterval(interval);
  }, [plan?.plan_id]);
  useEffect(() => { if (historyRef.current) historyRef.current.scrollTop = historyRef.current.scrollHeight; }, [messages?.length]);

  const send = async () => {
    const body = text.trim(); if (!body || !plan?.plan_id) return; setText('');
    try { const response = await api<{ result: { messages: TripMessageItem[] } }>('trip-messages/send.php', 'POST', { plan_id: plan.plan_id, body }, boot.csrf_token); setMessages(response.result.messages); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Message could not be sent.'); }
  };

  if (!plan) return <section className="planner-reference__body trip-messages"><div className="my-trips__empty"><p>No trip is open.</p><p>Open a trip from My Trips to see its group chat.</p><button onClick={onOpenMyTrips}>Go to My Trips</button></div></section>;

  return <section className="planner-reference__body trip-messages">
    <header className="planner-reference__top"><div><h1>Messages</h1><p>Group chat for "{plan.title}".</p></div><Link to={`/assistant?destination=${encodeURIComponent(plan.trip_summary?.destination || '')}`} className="trip-messages__ai-link"><Bot size={15} /> Ask Uthenga Assistant</Link></header>
    {error && <p className="planner-reference__error">{error}</p>}
    <div className="trip-messages__history" ref={historyRef}>
      {messages && messages.length === 0 && <p className="my-trips__loading">No messages yet. Say hello.</p>}
      {messages?.map(m => <p key={m.id} className={`trip-messages__bubble ${m.sender_id === boot.user?.id ? 'is-mine' : ''}`}><b>{m.sender_name}</b><span>{m.body}</span><time>{new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></p>)}
    </div>
    <div className="trip-messages__input"><input value={text} maxLength={2000} placeholder="Write a message…" onChange={event => setText(event.target.value)} onKeyDown={event => event.key === 'Enter' && void send()} /><button onClick={() => void send()}><Send size={16} /></button></div>
  </section>;
}

type SavedPlace = { listing_id: string; category: string; title: string; location: string; image: string | null; rating: number | null; saved_at: string | null };

// A real view over the existing, already-live `wishlist` table — saving and
// unsaving both go through the same request_api.php `toggle_wishlist`
// action already used across the marketplace's listing pages.
function SavedPlacesWorkspace({ boot }: { boot: Boot }) {
  const [places, setPlaces] = useState<SavedPlace[] | null>(null);
  const [category, setCategory] = useState('all');
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState('');

  const load = () => api<{ result: { places: SavedPlace[] } }>('saved-places/list.php')
    .then(response => { setPlaces(response.result.places); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load your saved places.'));
  useEffect(() => { void load(); }, []);

  const categories = useMemo(() => ['all', ...Array.from(new Set((places || []).map(p => p.category)))], [places]);
  const filtered = (places || []).filter(p => category === 'all' || p.category === category);

  const unsave = async (listingId: string) => {
    setBusyId(listingId); setError('');
    try {
      const body = new URLSearchParams({ action: 'toggle_wishlist', listing_id: listingId, csrf_token: boot.csrf_token || '' });
      const response = await fetch(`${runtimeBase}request_api.php`, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
      const data = await response.json().catch(() => ({}));
      if (!data.success) throw new Error(data.message || 'Could not remove this saved place.');
      await load();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not remove this saved place.'); }
    finally { setBusyId(''); }
  };

  return <section className="planner-reference__body saved-places">
    <header className="planner-reference__top"><div><h1>Saved Places</h1><p>Keep places you're interested in for later.</p></div></header>
    <nav className="planner-tabstrip">{categories.map(c => <button key={c} className={category === c ? 'is-active' : ''} onClick={() => setCategory(c)}>{c === 'all' ? 'All' : c.charAt(0).toUpperCase() + c.slice(1)}</button>)}</nav>
    {error && <p className="planner-reference__error">{error}</p>}
    {!places && !error && <p className="my-trips__loading">Loading your saved places…</p>}
    {places && places.length === 0 && <div className="my-trips__empty"><p>No saved places yet.</p><p>Use the save icon on any listing across Uthenga to add it here.</p></div>}
    {places && places.length > 0 && filtered.length === 0 && <div className="my-trips__empty"><p>No saved places match this filter.</p></div>}
    <div className="saved-places__grid">
      {filtered.map(p => <article key={p.listing_id} className="saved-places__card">
        {p.image && <img src={p.image} alt="" />}
        <div className="saved-places__body">
          <b>{p.title}</b>
          <small>{p.location}</small>
          <div className="saved-places__meta"><span>{p.category}</span>{p.rating !== null && <span>★ {p.rating.toFixed(1)}</span>}</div>
          <button disabled={busyId === p.listing_id} onClick={() => void unsave(p.listing_id)}>{busyId === p.listing_id ? 'Removing…' : 'Remove'}</button>
        </div>
      </article>)}
    </div>
  </section>;
}

type CustomerDocument = {
  id: string; category: string; label: string; trip_id: string | null; visibility: 'personal' | 'trip'; original_name: string; mime_type: string;
  size_bytes: number; expiry_date: string | null; expiry_status: 'none' | 'valid' | 'expiring_soon' | 'expired'; days_remaining: number | null; sensitive: boolean; created_at: string;
};
const DOCUMENT_CATEGORY_LABELS: Record<string, string> = { personal: 'Personal', travel: 'Travel', reservation: 'Reservation', financial: 'Financial', trip_document: 'Trip Document', other: 'Other' };

// A personal/trip document wallet built on customer_documents — real
// upload/storage/serving (never a public file path; documents/file.php
// checks ownership or real trip-collaborator visibility on every request).
// Expiry badges are plain date math (see CustomerDocuments.php), never a
// guess. "Sensitive" only blurs until revealed client-side — not device
// biometric re-authentication, which this web context can't provide.
function DocumentsWorkspace({ boot }: { boot: Boot }) {
  const [documents, setDocuments] = useState<CustomerDocument[] | null>(null);
  const [category, setCategory] = useState('all');
  const [error, setError] = useState('');
  const [showUpload, setShowUpload] = useState(false);
  const [busyId, setBusyId] = useState('');
  const [revealed, setRevealed] = useState<Set<string>>(new Set());

  const load = () => api<{ result: { documents: CustomerDocument[] } }>('documents/list.php')
    .then(response => { setDocuments(response.result.documents); setError(''); })
    .catch(reason => setError(reason instanceof Error ? reason.message : 'Could not load your documents.'));
  useEffect(() => { void load(); }, []);

  const categories = useMemo(() => ['all', ...Array.from(new Set((documents || []).map(d => d.category)))], [documents]);
  const filtered = (documents || []).filter(d => category === 'all' || d.category === category);

  const remove = async (id: string) => {
    if (!window.confirm('Delete this document? This cannot be undone.')) return;
    setBusyId(id); setError('');
    try { const response = await api<{ result: { documents: CustomerDocument[] } }>('documents/delete.php', 'POST', { document_id: id }, boot.csrf_token); setDocuments(response.result.documents); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not delete this document.'); }
    finally { setBusyId(''); }
  };
  const toggleReveal = (id: string) => setRevealed(current => { const next = new Set(current); if (next.has(id)) next.delete(id); else next.add(id); return next; });

  return <section className="planner-reference__body documents">
    <header className="planner-reference__top"><div><h1>Documents</h1><p>Keep your travel documents secure and easy to find.</p></div><button onClick={() => setShowUpload(true)}>Upload</button></header>
    <nav className="planner-tabstrip">{categories.map(c => <button key={c} className={category === c ? 'is-active' : ''} onClick={() => setCategory(c)}>{c === 'all' ? 'All' : DOCUMENT_CATEGORY_LABELS[c] || c}</button>)}</nav>
    {error && <p className="planner-reference__error">{error}</p>}
    {!documents && !error && <p className="my-trips__loading">Loading your documents…</p>}
    {documents && documents.length === 0 && <div className="my-trips__empty"><p>No travel documents yet.</p><p>Keep your important reservations, IDs and travel files together.</p><button onClick={() => setShowUpload(true)}>Upload Document</button></div>}
    {documents && documents.length > 0 && filtered.length === 0 && <div className="my-trips__empty"><p>No documents match this filter.</p></div>}
    <div className="documents__grid">
      {filtered.map(d => <article key={d.id} className="documents__card">
        <header><b>{d.label}</b>{d.expiry_status !== 'none' && <span className={`my-trips__status is-${d.expiry_status}`}>{d.expiry_status === 'expiring_soon' ? 'Expiring Soon' : d.expiry_status === 'expired' ? 'Expired' : 'Valid'}</span>}</header>
        <p className="documents__meta">{DOCUMENT_CATEGORY_LABELS[d.category] || d.category}{d.expiry_date && ` · Expires ${d.expiry_date}`}{d.trip_id && d.visibility === 'trip' && ' · Shared with trip'}</p>
        <p className="documents__meta">{d.original_name} · {Math.max(1, Math.round(d.size_bytes / 1024))} KB</p>
        {d.sensitive && !revealed.has(d.id)
          ? <button className="documents__reveal" onClick={() => toggleReveal(d.id)}>Sensitive document — Reveal</button>
          : <div className="documents__actions">
              <a href={`${runtimeBase}api/tie/documents/file.php?document_id=${encodeURIComponent(d.id)}`} target="_blank" rel="noopener noreferrer">Open</a>
              <button disabled={busyId === d.id} onClick={() => void remove(d.id)}>{busyId === d.id ? 'Deleting…' : 'Delete'}</button>
            </div>}
      </article>)}
    </div>
    {showUpload && <UploadDocumentModal boot={boot} onClose={() => setShowUpload(false)} onUploaded={docs => { setDocuments(docs); setShowUpload(false); }} />}
  </section>;
}

function UploadDocumentModal({ boot, onClose, onUploaded }: { boot: Boot; onClose: () => void; onUploaded: (documents: CustomerDocument[]) => void }) {
  const [trips, setTrips] = useState<MyTripSummary[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [category, setCategory] = useState('personal');
  const [label, setLabel] = useState('');
  const [tripId, setTripId] = useState('');
  const [visibility, setVisibility] = useState<'personal' | 'trip'>('personal');
  const [expiryDate, setExpiryDate] = useState('');
  const [sensitive, setSensitive] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => { api<{ result: { trips: MyTripSummary[] } }>('plans/list.php').then(response => setTrips(response.result.trips)).catch(() => undefined); }, []);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!file || !label.trim()) return;
    setBusy(true); setError('');
    try {
      const form = new FormData();
      form.append('file', file); form.append('category', category); form.append('label', label.trim());
      if (tripId) { form.append('trip_id', tripId); form.append('visibility', visibility); }
      if (expiryDate) form.append('expiry_date', expiryDate);
      if (sensitive) form.append('sensitive', '1');
      if (boot.csrf_token) form.append('csrf_token', boot.csrf_token);
      const response = await fetch(`${runtimeBase}api/tie/documents/upload.php`, { method: 'POST', credentials: 'include', body: form });
      const data = await response.json().catch(() => ({}));
      if (!data.success) throw new Error(data.error?.message || 'Could not upload this document.');
      onUploaded(data.result.documents);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not upload this document.'); }
    finally { setBusy(false); }
  };

  return <div className="doc-modal-backdrop" onMouseDown={onClose}><form className="doc-modal" onMouseDown={event => event.stopPropagation()} onSubmit={submit}>
    <header><h3>Upload Document</h3><button type="button" onClick={onClose}>×</button></header>
    <div className="doc-modal__body">
      <label>File<input type="file" accept="application/pdf,image/jpeg,image/png" required onChange={event => setFile(event.target.files?.[0] || null)} /></label>
      <label>Name<input required value={label} onChange={event => setLabel(event.target.value)} placeholder="e.g. Passport" /></label>
      <label>Category<select value={category} onChange={event => setCategory(event.target.value)}>{Object.entries(DOCUMENT_CATEGORY_LABELS).map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select></label>
      <label>Attach to trip (optional)<select value={tripId} onChange={event => setTripId(event.target.value)}><option value="">None</option>{trips.map(t => <option key={t.plan_id} value={t.plan_id}>{t.title}</option>)}</select></label>
      {tripId && <label>Visible to<select value={visibility} onChange={event => setVisibility(event.target.value as 'personal' | 'trip')}><option value="personal">Only me</option><option value="trip">Trip members</option></select></label>}
      <label>Expiry date (optional)<input type="date" value={expiryDate} onChange={event => setExpiryDate(event.target.value)} /></label>
      <label className="doc-modal__check"><input type="checkbox" checked={sensitive} onChange={event => setSensitive(event.target.checked)} /> Sensitive document</label>
    </div>
    {error && <p className="planner-reference__error">{error}</p>}
    <footer><button disabled={busy} type="submit">{busy ? 'Uploading…' : 'Upload'}</button></footer>
  </form></div>;
}

type CustomerPreferencesData = {
  pace?: string; planning_style?: string; interests?: string[]; accommodation_types?: string[]; accommodation_level?: string;
  transport_modes?: string[]; transport_priority?: string; food_cuisines?: string[]; dietary?: string[]; budget_style?: string;
  accessibility_mobility?: string; ai_use_preferences?: boolean; ai_use_saved_places?: boolean; ai_auto_add_recommendations?: boolean;
  units_distance?: string; language?: string; region?: string; trip_defaults?: { purpose?: string; travellers?: number; budget?: number };
};
type NotificationPrefs = { push: boolean; email: boolean; sms: boolean };
const INTEREST_OPTIONS = ['Nature', 'Culture', 'History', 'Food', 'Business', 'Shopping', 'Nightlife', 'Sports', 'Adventure'];
const ACCOMMODATION_TYPE_OPTIONS = ['Hotel', 'Lodge', 'Guesthouse', 'Apartment'];
const TRANSPORT_MODE_OPTIONS = ['Quick Taxi', 'Bus', 'Private Vehicle', 'Walking'];
const CUISINE_OPTIONS = ['Local', 'Indian', 'African', 'Vegan', 'Fast Food'];
const DIETARY_OPTIONS = ['Vegan', 'Vegetarian', 'Halal', 'Gluten-free'];

// Notification toggles are real — the same users.push_notify/email_notify/
// sms_notify columns profile.php's existing form already reads and writes,
// and that UthengaTieNotificationService already gates delivery on. Every
// other selection here is genuinely persisted, but honestly NOT yet
// consumed by the recommendation engine's scoring — said so in the UI
// rather than implying invisible AI behavior that doesn't exist.
function PreferencesWorkspace({ boot, onSaved }: { boot: Boot; onSaved: (preferences: CustomerPreferencesData) => void }) {
  const [preferences, setPreferences] = useState<CustomerPreferencesData>({});
  const [notifications, setNotifications] = useState<NotificationPrefs>({ push: true, email: true, sms: false });
  const [loaded, setLoaded] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    api<{ result: { preferences: CustomerPreferencesData; notifications: NotificationPrefs } }>('preferences/get.php')
      .then(response => { setPreferences(response.result.preferences || {}); setNotifications(response.result.notifications); setLoaded(true); })
      .catch(reason => { setError(reason instanceof Error ? reason.message : 'Could not load your preferences.'); setLoaded(true); });
  }, []);

  const toggleListValue = (key: 'interests' | 'accommodation_types' | 'transport_modes' | 'food_cuisines' | 'dietary', value: string) => {
    setPreferences(current => {
      const list = Array.isArray(current[key]) ? [...(current[key] as string[])] : [];
      const next = list.includes(value) ? list.filter(v => v !== value) : [...list, value];
      return { ...current, [key]: next };
    });
  };

  const save = async () => {
    setBusy(true); setError(''); setSaved(false);
    try {
      const response = await api<{ result: { preferences: CustomerPreferencesData; notifications: NotificationPrefs } }>('preferences/save.php', 'POST', { preferences, notifications }, boot.csrf_token);
      setPreferences(response.result.preferences); setNotifications(response.result.notifications); setSaved(true); onSaved(response.result.preferences);
      window.setTimeout(() => setSaved(false), 3000);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save your preferences.'); }
    finally { setBusy(false); }
  };

  if (!loaded) return <section className="planner-reference__body preferences"><p className="my-trips__loading">Loading your preferences…</p></section>;

  return <section className="planner-reference__body preferences">
    <header className="planner-reference__top"><div><h1>Preferences</h1><p>Tell Uthenga how you like to travel.</p></div><button disabled={busy} onClick={() => void save()}>{busy ? 'Saving…' : saved ? 'Saved ✓' : 'Save Preferences'}</button></header>
    {error && <p className="planner-reference__error">{error}</p>}

    <article className="preferences__section">
      <h3>Travel Style</h3>
      <label>Pace<div className="preferences__choices">{['relaxed', 'balanced', 'fast_paced'].map(v => <button key={v} type="button" className={preferences.pace === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, pace: v }))}>{v.replace('_', ' ')}</button>)}</div></label>
      <label>Planning style<div className="preferences__choices">{['structured', 'balanced', 'flexible'].map(v => <button key={v} type="button" className={preferences.planning_style === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, planning_style: v }))}>{v}</button>)}</div></label>
      <label>Interests<div className="preferences__chips">{INTEREST_OPTIONS.map(v => <button key={v} type="button" className={(preferences.interests || []).includes(v) ? 'is-selected' : ''} onClick={() => toggleListValue('interests', v)}>{v}</button>)}</div></label>
    </article>

    <article className="preferences__section">
      <h3>Transport</h3>
      <label>Preferred modes<div className="preferences__chips">{TRANSPORT_MODE_OPTIONS.map(v => <button key={v} type="button" className={(preferences.transport_modes || []).includes(v) ? 'is-selected' : ''} onClick={() => toggleListValue('transport_modes', v)}>{v}</button>)}</div></label>
      <label>Prioritize<div className="preferences__choices">{['time', 'cost', 'balance'].map(v => <button key={v} type="button" className={preferences.transport_priority === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, transport_priority: v }))}>{v}</button>)}</div></label>
    </article>

    <article className="preferences__section">
      <h3>Accommodation</h3>
      <label>Preferred type<div className="preferences__chips">{ACCOMMODATION_TYPE_OPTIONS.map(v => <button key={v} type="button" className={(preferences.accommodation_types || []).includes(v) ? 'is-selected' : ''} onClick={() => toggleListValue('accommodation_types', v)}>{v}</button>)}</div></label>
      <label>Level<div className="preferences__choices">{['budget', 'mid_range', 'premium', 'luxury'].map(v => <button key={v} type="button" className={preferences.accommodation_level === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, accommodation_level: v }))}>{v.replace('_', ' ')}</button>)}</div></label>
    </article>

    <article className="preferences__section">
      <h3>Food &amp; Dietary</h3>
      <label>Cuisine<div className="preferences__chips">{CUISINE_OPTIONS.map(v => <button key={v} type="button" className={(preferences.food_cuisines || []).includes(v) ? 'is-selected' : ''} onClick={() => toggleListValue('food_cuisines', v)}>{v}</button>)}</div></label>
      <label>Dietary<div className="preferences__chips">{DIETARY_OPTIONS.map(v => <button key={v} type="button" className={(preferences.dietary || []).includes(v) ? 'is-selected' : ''} onClick={() => toggleListValue('dietary', v)}>{v}</button>)}</div></label>
    </article>

    <article className="preferences__section">
      <h3>Budget Style</h3>
      <div className="preferences__choices">{['budget_conscious', 'balanced', 'premium'].map(v => <button key={v} type="button" className={preferences.budget_style === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, budget_style: v }))}>{v.replace('_', ' ')}</button>)}</div>
    </article>

    <article className="preferences__section">
      <h3>Accessibility</h3>
      <div className="preferences__choices">{['none', 'reduced_mobility', 'wheelchair'].map(v => <button key={v} type="button" className={preferences.accessibility_mobility === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, accessibility_mobility: v }))}>{v.replace('_', ' ')}</button>)}</div>
    </article>

    <article className="preferences__section">
      <h3>Trip Defaults</h3>
      <p className="preferences__hint">Used to prefill a new trip's intake — never overrides a specific trip's own details.</p>
      <label>Default purpose<input value={preferences.trip_defaults?.purpose || ''} onChange={event => setPreferences(c => ({ ...c, trip_defaults: { ...c.trip_defaults, purpose: event.target.value } }))} placeholder="e.g. Business" /></label>
      <label>Default travellers<input type="number" min="1" value={preferences.trip_defaults?.travellers || ''} onChange={event => setPreferences(c => ({ ...c, trip_defaults: { ...c.trip_defaults, travellers: Number(event.target.value) || undefined } }))} /></label>
      <label>Default budget (MWK)<input type="number" min="0" value={preferences.trip_defaults?.budget || ''} onChange={event => setPreferences(c => ({ ...c, trip_defaults: { ...c.trip_defaults, budget: Number(event.target.value) || undefined } }))} /></label>
    </article>

    <article className="preferences__section">
      <h3>AI Personalization</h3>
      <p className="preferences__hint">Saved for you now — deeper AI use of these signals in recommendations is coming soon.</p>
      <label className="preferences__toggle"><input type="checkbox" checked={preferences.ai_use_preferences !== false} onChange={event => setPreferences(c => ({ ...c, ai_use_preferences: event.target.checked }))} /> Use my travel preferences</label>
      <label className="preferences__toggle"><input type="checkbox" checked={preferences.ai_use_saved_places !== false} onChange={event => setPreferences(c => ({ ...c, ai_use_saved_places: event.target.checked }))} /> Use saved places</label>
      <label className="preferences__toggle"><input type="checkbox" checked={!!preferences.ai_auto_add_recommendations} onChange={event => setPreferences(c => ({ ...c, ai_auto_add_recommendations: event.target.checked }))} /> Auto-add AI recommendations to my plan</label>
    </article>

    <article className="preferences__section">
      <h3>Notifications</h3>
      <label className="preferences__toggle"><input type="checkbox" checked={notifications.push} onChange={event => setNotifications(c => ({ ...c, push: event.target.checked }))} /> Push</label>
      <label className="preferences__toggle"><input type="checkbox" checked={notifications.email} onChange={event => setNotifications(c => ({ ...c, email: event.target.checked }))} /> Email</label>
      <label className="preferences__toggle"><input type="checkbox" checked={notifications.sms} onChange={event => setNotifications(c => ({ ...c, sms: event.target.checked }))} /> SMS</label>
    </article>

    <article className="preferences__section">
      <h3>Language &amp; Units</h3>
      <label>Language<input value={preferences.language || ''} onChange={event => setPreferences(c => ({ ...c, language: event.target.value }))} placeholder="English" /></label>
      <label>Region<input value={preferences.region || ''} onChange={event => setPreferences(c => ({ ...c, region: event.target.value }))} placeholder="Malawi" /></label>
      <label>Distance units<div className="preferences__choices">{['km', 'miles'].map(v => <button key={v} type="button" className={preferences.units_distance === v ? 'is-selected' : ''} onClick={() => setPreferences(c => ({ ...c, units_distance: v }))}>{v}</button>)}</div></label>
    </article>
  </section>;
}

function AssistantWorkspace({ boot }: { boot: Boot }) {
  const today = new Date().toISOString().slice(0, 10);
  const params = new URLSearchParams(window.location.search);
  const [destination, setDestination] = useState(params.get('destination') || 'Lilongwe City Centre');
  const [date, setDate] = useState(today);
  const [travellers, setTravellers] = useState('1');
  const [budget, setBudget] = useState('');
  const [message, setMessage] = useState(params.get('message') || 'Help me find the best available travel options.');
  const [response, setResponse] = useState<any>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [conversationId] = useState(() => {
    const key = 'uthenga.react.conversation_id';
    const existing = sessionStorage.getItem(key);
    if (existing) return existing;
    const next = `react-${crypto.randomUUID()}`;
    sessionStorage.setItem(key, next);
    return next;
  });
  const ask = async (event?: React.FormEvent, nextMessage?: string) => {
    event?.preventDefault(); setBusy(true); setError('');
    const requestMessage = nextMessage || message;
    try {
      const result = await api<{ conversation: any }>('conversation/assistant.php', 'POST', { conversation_id: conversationId, message: requestMessage, destination, start_date: date, end_date: date, travellers: Number(travellers), budget: budget === '' ? null : Number(budget), currency: 'MWK', preferences: [], travel_mode: null, limit: 8 }, boot.csrf_token);
      if (nextMessage) setMessage(nextMessage);
      setResponse(result.conversation);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'The assistant is unavailable.'); }
    finally { setBusy(false); }
  };
  const quickUpdate = (text: string) => void ask(undefined, text);
  return <main className="assistant-workspace"><header><Link to="/">← Travel workspace</Link><span>VERIFIED EVIDENCE ONLY</span></header><section><p className="eyebrow">UTHENGA CONVERSATION</p><h1>Ask the travel intelligence engine.</h1><p>The assistant explains validated Uthenga evidence. It cannot create a booking or change payment state.</p><form onSubmit={ask}><label>Destination<input value={destination} onChange={e => setDestination(e.target.value)} required /></label><label>Travel date<input type="date" value={date} min={today} onChange={e => setDate(e.target.value)} required /></label><label>Travellers<input type="number" min="1" max="20" value={travellers} onChange={e => setTravellers(e.target.value)} required /></label><label>Budget · MWK<input type="number" min="0" value={budget} onChange={e => setBudget(e.target.value)} placeholder="Optional" /></label><label className="wide">Your request<textarea value={message} onChange={e => setMessage(e.target.value)} rows={4} required /></label><button className="button button--primary" disabled={busy}>{busy ? 'Checking trusted travel evidence…' : 'Ask Uthenga'}</button></form>{response && <div className="update-actions"><span>Update this trip:</span>{['Make it cheaper', 'Show the most comfortable options', 'Compare the verified options', 'Change my travel date'].map(action => <button key={action} disabled={busy} onClick={() => quickUpdate(action)}>{action}</button>)}</div>}{error && <p className="inline-error">{error}</p>}{response && <article className="assistant-response"><p className="eyebrow">UTHENGA RESPONSE</p><h2>{response.message}</h2><div className="assistant-recommendations">{(response.recommendations || []).map((item: any) => <div key={item.id}><b>{item.title}</b><span>{item.category} · {item.price?.amount ? `MWK ${Number(item.price.amount).toLocaleString()}` : 'Price on request'}</span></div>)}</div><small>{response.diagnostics?.php_fallback_used ? 'PHP deterministic conversation fallback used.' : 'FastAPI explanation bridge used.'}</small></article>}</section></main>;
}

type VendorProfile = { id: string; type: string; name: string; status: string; active: boolean; listing_id?: string | null; configuration: Record<string, unknown> };
type VendorWorkspaceData = { profiles: { profiles: VendorProfile[]; supported_profiles: { type: string; label: string; description: string }[] }; inventory: { id: string; type: string; title: string; location: string; image: string | null; active: boolean }[]; metrics: { active_services: number; inventory_count: number; booking_count: number; gross_revenue: number; currency: string } };

function VendorWorkspace({ boot }: { boot: Boot }) {
  useEffect(() => {
    window.location.href = boot.legacy_fallbacks?.vendor_workspace || '/uthenga/vendor/dashboard.php';
  }, [boot]);

  return (
    <main className="vendor-workspace" style={{ padding: '4rem 2rem', textAlign: 'center' }}>
      <p className="eyebrow">UTHENGA VENDOR CONTROL CENTER</p>
      <h1>Opening Vendor Dashboard…</h1>
      <p style={{ marginTop: '1rem', color: '#9bb8cc' }}>
        <a href={boot.legacy_fallbacks?.vendor_workspace || '/uthenga/vendor/dashboard.php'} style={{ color: '#4ee7ac', fontWeight: 800 }}>
          Click here to go directly to Vendor Dashboard →
        </a>
      </p>
    </main>
  );
}

function ServiceReviewDesk({ boot }: { boot: Boot }) {
  const [reviews, setReviews] = useState<any[]>([]); const [notice, setNotice] = useState(''); const [busy, setBusy] = useState('');
  const load = async () => { try { setReviews((await api<{ reviews: any[] }>('vendor/reviews.php')).reviews); } catch (error) { setNotice(error instanceof Error ? error.message : 'Unable to load service reviews.'); } };
  useEffect(() => { void load(); }, []);
  const decide = async (profileId: string, decision: 'APPROVE' | 'REJECT') => { const note = decision === 'REJECT' ? window.prompt('Explain the required changes for this vendor.') || '' : ''; if (decision === 'REJECT' && !note) return; setBusy(profileId); try { await api('vendor/profiles.php', 'POST', { action: 'review_profile', profile_id: profileId, decision, note }, boot.csrf_token); await load(); setNotice(decision === 'APPROVE' ? 'Service approved and published.' : 'Service returned to private draft.'); } catch (error) { setNotice(error instanceof Error ? error.message : 'Unable to record review outcome.'); } finally { setBusy(''); } };
  return <main className="service-shell"><header><Link to="/vendor">← Vendor workspace</Link><span>AUTHORISED REVIEW</span></header><section><p className="eyebrow">SERVICE REVIEW DESK</p><h1>Review vendor services safely.</h1><p>Approval creates marketplace inventory only after category validation succeeds.</p>{notice && <p className="settings-notice">{notice}</p>}<div className="service-shell-grid">{reviews.length ? reviews.map(review => <article key={review.id}><b>{review.profile_name}</b><span>{review.profile_type} · {review.vendor_name || review.vendor_id}</span><span>{review.configuration?.location || review.configuration?.property_name || 'Configuration supplied'}</span><div><button className="button button--primary" disabled={busy === review.id} onClick={() => void decide(review.id, 'APPROVE')}>Approve & publish</button><button className="button button--quiet" disabled={busy === review.id} onClick={() => void decide(review.id, 'REJECT')}>Request changes</button></div></article>) : <article><b>No services awaiting review.</b><span>Submitted vendor services will appear here.</span></article>}</div></section></main>;
}

function ServiceWorkspaceShell({ boot }: { boot: Boot }) {
  const { kind = 'service' } = useParams();
  const labels: Record<string, string> = { accommodation: 'Accommodation control', event: 'Event control', tour: 'Tour control' };
  const title = labels[kind] || 'Service control';
  return <main className="service-shell"><header><Link to="/vendor">← Service switcher</Link><span>PRIVATE DRAFT</span></header><section><p className="eyebrow">UTHENGA SERVICE WORKSPACE</p><h1>{title}</h1><p>The React shell is active. Its category-specific settings, inventory, calendar, ticket, and check-in controls are the next replacement work and will be released only with their authoritative PHP APIs.</p><div className="service-shell-grid"><article><b>1. Profile created</b><span>Service identity and category configuration remain private until a complete setup API is available.</span><span className="muted">No public listing has been created.</span></article><article><b>2. Review gate</b><span>Publication is intentionally unavailable until category validation can prove capacity, availability and pricing.</span><span className="muted">No legacy form is opened from this React screen.</span></article><article><b>3. Return to operations</b><span>Choose another service dashboard or manage the active transport operation.</span><Link className="button button--quiet" to="/vendor">Return to vendor workspace</Link></article></div></section></main>;
}
function Metric({ label, value, accent, suffix = '' }: { label: string; value: number; accent: string; suffix?: string }) { return <article className={'metric metric--' + accent}><b>{value}{suffix}</b><span>{label}</span></article>; }

// The first Driver dashboard retained a PHP settings fallback link during the
// migration. Inside the React service shell that exact action now opens the
// native settings route, while the PHP page remains available outside React.
document.addEventListener('click', event => {
  const link = (event.target as HTMLElement).closest('a[href$="#settings"]');
  if (link && window.location.hash.startsWith('#/driver')) { event.preventDefault(); window.location.hash = '#/driver/settings'; }
});

createRoot(document.getElementById('root')!).render(<HashRouter><App /></HashRouter>);
