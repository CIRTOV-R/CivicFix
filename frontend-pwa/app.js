const $ = (id) => document.getElementById(id);

const API_BASE = '/api/v1';
const TOKEN_KEY = 'civicfix.token';
const USER_KEY = 'civicfix.user';
const DB_NAME = 'civicfix';
const DB_VERSION = 1;
const OUTBOX_STORE = 'outbox';
const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const MAX_PHOTO_BYTES = 350 * 1024;

const STAFF_ROLES = new Set(['SUPERVISOR', 'ADMINISTRADOR']);
const CREW_ROLES = new Set(['CUADRILLA', 'SUPERVISOR', 'ADMINISTRADOR']);
const PRIORITY_COLORS = { BAJA: '#22c55e', MEDIA: '#f59e0b', ALTA: '#ef4444', CRITICA: '#a855f7' };
const ROLE_LABELS = { CIUDADANO: 'Ciudadano', CUADRILLA: 'Cuadrilla', SUPERVISOR: 'Supervisor', ADMINISTRADOR: 'Administrador' };

const state = {
  token: localStorage.getItem(TOKEN_KEY) || null,
  user: readStoredUser(),
  categories: [],
  incidents: [],
  detail: null,
  dashboard: null,
  activeView: 'report',
  reportPhoto: null,
  afterPhoto: null,
  watchId: null,
  lastPosition: null,
  syncing: false,
  loading: 0,
  map: null,
  markerLayer: null,
  focusPoint: null
};

class ApiError extends Error {
  constructor(message, status = 0, network = false) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.network = network;
  }
}

function readStoredUser() {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function roleOf(user = state.user) {
  const role = user?.role;
  const value = typeof role === 'object' && role !== null ? (role.name || role.slug || role.label) : role;
  return String(value || '').toUpperCase();
}

const isStaff = () => STAFF_ROLES.has(roleOf());
const isCrew = () => CREW_ROLES.has(roleOf());
const isAdmin = () => roleOf() === 'ADMINISTRADOR';

function safe(value, fallback = '—') {
  return value === null || value === undefined || value === '' ? fallback : String(value);
}

function humanize(value) {
  return String(value || '—').replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

function asArray(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  try {
    return new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  } catch {
    return date.toLocaleString();
  }
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined && text !== null) node.textContent = String(text);
  return node;
}

function toast(message, kind = 'info', duration = 4200) {
  const region = $('toastRegion');
  const node = el('div', `toast ${kind}`, message);
  region.append(node);
  while (region.children.length > 4) region.firstElementChild.remove();
  window.setTimeout(() => node.remove(), duration);
}

async function withLoading(task) {
  state.loading += 1;
  $('loading').hidden = false;
  try {
    return await task();
  } finally {
    state.loading = Math.max(0, state.loading - 1);
    if (state.loading === 0) $('loading').hidden = true;
  }
}

function clearSession() {
  state.token = null;
  state.user = null;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

function setToken(token) {
  state.token = token || null;
  if (state.token) localStorage.setItem(TOKEN_KEY, state.token);
  else localStorage.removeItem(TOKEN_KEY);
}

function setUser(user) {
  state.user = user || null;
  if (state.user) localStorage.setItem(USER_KEY, JSON.stringify(state.user));
  else localStorage.removeItem(USER_KEY);
}

function handleUnauthorized() {
  clearSession();
  showAuth();
  toast('Tu sesión ha terminado. Inicia sesión nuevamente.', 'error');
}

async function api(path, options = {}) {
  const { method = 'GET', json, body, headers = {}, authRequest = false } = options;
  const init = {
    method,
    credentials: 'omit',
    headers: { Accept: 'application/json', ...headers }
  };
  if (state.token && !authRequest) init.headers.Authorization = `Bearer ${state.token}`;
  if (json !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(json);
  } else if (body !== undefined) {
    init.body = body;
  }

  let response;
  try {
    response = await fetch(`${API_BASE}${path}`, init);
  } catch {
    throw new ApiError('No se pudo conectar con el servidor.', 0, true);
  }

  if (response.status === 401 && !authRequest) {
    handleUnauthorized();
    throw new ApiError('Sesión no autorizada.', 401);
  }

  let payload = null;
  const text = await response.text();
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const message = payload?.message || payload?.error || `Error ${response.status}`;
    throw new ApiError(typeof message === 'string' ? message : 'La solicitud no pudo completarse.', response.status);
  }
  return payload ?? { data: null };
}

function extractToken(payload) {
  return payload?.data?.token || payload?.token || null;
}

function extractUser(payload) {
  const data = payload?.data;
  if (data?.user) return data.user;
  if (data && (data.id || data.uuid) && (data.name || data.email)) return data;
  return payload?.user || null;
}

function uuid() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = Math.floor(Math.random() * 16);
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

let dbPromise = null;

function openDb() {
  if (!('indexedDB' in globalThis)) return Promise.reject(new Error('IndexedDB no disponible.'));
  if (!dbPromise) {
    dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
          const store = db.createObjectStore(OUTBOX_STORE, { keyPath: 'idempotencyKey' });
          store.createIndex('status', 'status', { unique: false });
          store.createIndex('createdAt', 'createdAt', { unique: false });
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  return dbPromise;
}

function outboxAll() {
  return openDb().then((db) => new Promise((resolve, reject) => {
    const request = db.transaction(OUTBOX_STORE, 'readonly').objectStore(OUTBOX_STORE).getAll();
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  }));
}

function outboxPut(record) {
  return openDb().then((db) => new Promise((resolve, reject) => {
    const transaction = db.transaction(OUTBOX_STORE, 'readwrite');
    transaction.objectStore(OUTBOX_STORE).put(record);
    transaction.oncomplete = () => resolve();
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error);
  }));
}

function outboxDelete(key) {
  return openDb().then((db) => new Promise((resolve, reject) => {
    const transaction = db.transaction(OUTBOX_STORE, 'readwrite');
    transaction.objectStore(OUTBOX_STORE).delete(key);
    transaction.oncomplete = () => resolve();
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error);
  }));
}

async function updatePendingBadge() {
  const badge = $('pendingCount');
  try {
    const rows = await outboxAll();
    if (!rows.length) {
      badge.hidden = true;
      return;
    }
    badge.textContent = `${rows.length} reporte(s) pendiente(s) de sincronización.`;
    badge.hidden = false;
  } catch {
    badge.hidden = true;
  }
}

function loadImageSource(file) {
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file).then((image) => ({
      image,
      width: image.width,
      height: image.height,
      close: () => image.close?.()
    }));
  }
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => resolve({
      image,
      width: image.naturalWidth,
      height: image.naturalHeight,
      close: () => URL.revokeObjectURL(url)
    });
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('No se pudo leer la imagen.'));
    };
    image.src = url;
  });
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

async function compressImage(file, maxBytes = MAX_PHOTO_BYTES) {
  if (!file?.type?.startsWith('image/')) throw new Error('Selecciona un archivo de imagen.');
  const source = await loadImageSource(file);
  try {
    let width = source.width;
    let height = source.height;
    const maxDim = 1600;
    if (Math.max(width, height) > maxDim) {
      const scale = maxDim / Math.max(width, height);
      width = Math.round(width * scale);
      height = Math.round(height * scale);
    }
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    if (!context) throw new Error('No se pudo procesar la imagen.');
    let lastBlob = null;
    for (let attempt = 0; attempt < 6; attempt += 1) {
      canvas.width = width;
      canvas.height = height;
      context.drawImage(source.image, 0, 0, width, height);
      let quality = 0.82;
      while (quality >= 0.4) {
        let blob = await canvasToBlob(canvas, 'image/webp', quality);
        if (!blob || blob.type !== 'image/webp') blob = await canvasToBlob(canvas, 'image/jpeg', quality);
        if (blob) lastBlob = blob;
        if (blob && blob.size <= maxBytes) return blob;
        quality -= 0.1;
      }
      width = Math.max(320, Math.round(width * 0.75));
      height = Math.max(240, Math.round(height * 0.75));
    }
    throw new Error(lastBlob ? 'La imagen no pudo reducirse por debajo de 350 KB.' : 'No se pudo comprimir la imagen.');
  } finally {
    source.close();
  }
}

function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(reader.error || new Error('No se pudo leer la imagen.'));
    reader.readAsDataURL(blob);
  });
}

function dataUrlToBlob(dataUrl) {
  const match = /^data:([^;,]+);base64,(.*)$/.exec(String(dataUrl || ''));
  if (!match) throw new Error('Imagen en cola no válida.');
  const binary = atob(match[2]);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: match[1] });
}

function uploadFile(blob, name) {
  try {
    return new File([blob], name, { type: blob.type || 'image/jpeg' });
  } catch {
    return blob;
  }
}

function photoName(blob) {
  return blob.type === 'image/webp' ? 'photo.webp' : 'photo.jpg';
}

function setPreview(target, blob) {
  const wrapper = target === 'report' ? $('photoPreviewWrap') : $('afterPreviewWrap');
  const image = target === 'report' ? $('photoPreview') : $('afterPreview');
  const current = target === 'report' ? state.reportPhoto : state.afterPhoto;
  if (current?.url) URL.revokeObjectURL(current.url);
  if (!blob) {
    if (target === 'report') state.reportPhoto = null;
    else state.afterPhoto = null;
    image.removeAttribute('src');
    wrapper.hidden = true;
    return;
  }
  const url = URL.createObjectURL(blob);
  if (target === 'report') state.reportPhoto = { blob, url };
  else state.afterPhoto = { blob, url };
  image.src = url;
  wrapper.hidden = false;
}

async function handlePhotoPick(input, target) {
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  try {
    const blob = await withLoading(() => compressImage(file));
    setPreview(target, blob);
    toast('Foto lista para enviar.', 'success');
  } catch (error) {
    toast(error.message || 'No se pudo preparar la foto.', 'error');
  }
}

function updateGeoFromPosition(position) {
  const { latitude, longitude, accuracy } = position.coords;
  state.lastPosition = position;
  $('latitude').value = Number(latitude).toFixed(6);
  $('longitude').value = Number(longitude).toFixed(6);
  const precision = Number.isFinite(accuracy) ? ` ± ${Math.round(accuracy)} m` : '';
  $('geoStatus').textContent = `Ubicación: ${Number(latitude).toFixed(5)}, ${Number(longitude).toFixed(5)}${precision}`;
}

function geoErrorMessage(error) {
  if (error?.code === 1) return 'Permiso de ubicación denegado. Ingresa las coordenadas manualmente.';
  if (error?.code === 2) return 'No se pudo obtener la ubicación. Ingresa las coordenadas manualmente.';
  if (error?.code === 3) return 'La ubicación tardó demasiado. Ingresa las coordenadas manualmente.';
  return error?.message || 'Ubicación no disponible. Ingresa las coordenadas manualmente.';
}

function getCurrentPosition(options = { enableHighAccuracy: true, timeout: 12000, maximumAge: 10000 }) {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Este dispositivo no admite geolocalización.'));
      return;
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, options);
  });
}

function probeLocation() {
  getCurrentPosition({ enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 })
    .then(updateGeoFromPosition)
    .catch(() => {});
}

function stopLocationWatch() {
  if (state.watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(state.watchId);
  state.watchId = null;
  $('locateBtn').textContent = 'Usar mi ubicación';
  $('stopLocateBtn').hidden = true;
}

function startLocationWatch() {
  if (state.watchId !== null) {
    stopLocationWatch();
    return;
  }
  if (!navigator.geolocation) {
    $('geoStatus').textContent = 'Geolocalización no disponible. Ingresa las coordenadas manualmente.';
    toast('Geolocalización no disponible en este dispositivo.', 'error');
    return;
  }
  $('locateBtn').textContent = 'Ubicación activa';
  $('stopLocateBtn').hidden = false;
  state.watchId = navigator.geolocation.watchPosition(
    updateGeoFromPosition,
    (error) => {
      stopLocationWatch();
      const message = geoErrorMessage(error);
      $('geoStatus').textContent = message;
      toast(message, 'error');
      getCurrentPosition().then(updateGeoFromPosition).catch(() => {});
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
  );
}

function readCoords() {
  const latitude = Number.parseFloat($('latitude').value);
  const longitude = Number.parseFloat($('longitude').value);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
    throw new Error('Ingresa latitud y longitud válidas.');
  }
  return { latitude, longitude };
}

async function actionCoordinates(incident) {
  try {
    const position = await getCurrentPosition({ enableHighAccuracy: true, timeout: 8000, maximumAge: 15000 });
    return { latitude: position.coords.latitude, longitude: position.coords.longitude };
  } catch {
    const latitude = Number(incident?.latitude);
    const longitude = Number(incident?.longitude);
    if (Number.isFinite(latitude) && Number.isFinite(longitude)) return { latitude, longitude };
    return readCoords();
  }
}

function validateForm(form) {
  let valid = true;
  const controls = [...form.querySelectorAll('input, select, textarea')].filter((control) => !control.closest('[hidden]'));
  for (const control of controls) {
    const invalid = !control.checkValidity();
    control.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    if (invalid) valid = false;
  }
  return valid;
}

function setAuthMode(mode) {
  const register = mode === 'register';
  $('tabLogin').classList.toggle('is-active', !register);
  $('tabRegister').classList.toggle('is-active', register);
  $('tabLogin').setAttribute('aria-selected', String(!register));
  $('tabRegister').setAttribute('aria-selected', String(register));
  $('nameField').hidden = !register;
  $('confirmField').hidden = !register;
  $('roleField').hidden = !register;
  $('authPassword').autocomplete = register ? 'new-password' : 'current-password';
  $('authSubmit').textContent = register ? 'Crear cuenta' : 'Entrar';
  $('authError').hidden = true;
  $('authForm').dataset.mode = mode;
}

async function handleAuthSubmit(event) {
  event.preventDefault();
  const register = $('authForm').dataset.mode === 'register';
  $('authError').hidden = true;
  if (!validateForm($('authForm'))) {
    $('authError').textContent = 'Revisa los campos marcados.';
    $('authError').hidden = false;
    return;
  }
  const password = $('authPassword').value;
  if (register && password !== $('authConfirmation').value) {
    $('authError').textContent = 'Las contraseñas no coinciden.';
    $('authError').hidden = false;
    return;
  }
  const payload = register
    ? {
        name: $('authName').value.trim(),
        email: $('authEmail').value.trim(),
        password,
        password_confirmation: $('authConfirmation').value,
        role: $('authRole').value || undefined
      }
    : { email: $('authEmail').value.trim(), password };

  try {
    await withLoading(async () => {
      const response = await api(register ? '/auth/register' : '/auth/login', {
        method: 'POST',
        json: payload,
        authRequest: true
      });
      setToken(extractToken(response));
      setUser(extractUser(response));
      $('authForm').reset();
      showApp();
      await bootstrapData();
      toast(register ? 'Cuenta creada. Bienvenido.' : 'Sesión iniciada.', 'success');
    });
  } catch (error) {
    $('authError').textContent = error.message || 'No se pudo completar la solicitud.';
    $('authError').hidden = false;
    toast(error.message || 'No se pudo completar la solicitud.', 'error');
  }
}

async function handleLogout() {
  if (state.token) {
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {
      toast('Se cerró la sesión localmente.', 'info');
    }
  }
  stopLocationWatch();
  clearSession();
  state.detail = null;
  state.dashboard = null;
  state.incidents = [];
  setPreview('report', null);
  setPreview('after', null);
  showAuth();
}

async function restoreSession() {
  try {
    const response = await withLoading(() => api('/auth/me'));
    setUser(extractUser(response));
  } catch (error) {
    if (error.network && state.user) {
      toast('Sin conexión: se restauró la sesión local.', 'info');
    } else {
      clearSession();
      showAuth();
      return;
    }
  }
  showApp();
  await bootstrapData();
}

async function bootstrapData() {
  await loadCategories(true);
  await loadIncidents(true);
  if (isStaff()) await loadDashboard(true);
  await updatePendingBadge();
  syncOutbox();
}

function showAuth() {
  $('authView').hidden = false;
  $('appView').hidden = true;
  $('topbar').hidden = true;
  $('tabbar').hidden = true;
  $('panelTab').hidden = true;
  if (state.activeView === 'panel') state.activeView = 'report';
}

function showApp() {
  $('authView').hidden = true;
  $('appView').hidden = false;
  $('topbar').hidden = false;
  $('tabbar').hidden = false;
  const role = roleOf();
  $('roleBadge').textContent = ROLE_LABELS[role] || humanize(role || 'Ciudadano');
  $('userName').textContent = safe(state.user?.name || state.user?.email, '');
  $('panelTab').hidden = !isStaff();
  $('crewSection').hidden = !isStaff();
  $('auditSection').hidden = !isAdmin();
  switchView(state.activeView === 'panel' && !isStaff() ? 'report' : state.activeView);
  probeLocation();
}

function switchView(view) {
  const target = view === 'panel' && !isStaff() ? 'report' : view;
  state.activeView = target;
  document.querySelectorAll('.view').forEach((section) => {
    section.hidden = section.id !== `view-${target}`;
  });
  document.querySelectorAll('.tab').forEach((tab) => {
    const active = tab.dataset.view === target;
    tab.classList.toggle('is-active', active);
    if (active) tab.setAttribute('aria-current', 'page');
    else tab.removeAttribute('aria-current');
  });
  if (target === 'map') initMap();
  if (target === 'panel' && isStaff()) loadDashboard(true);
  if (target === 'report' && !state.categories.length) loadCategories(true);
}

async function loadCategories(silent = false) {
  const run = async () => {
    const response = await api('/categories');
    state.categories = asArray(response.data);
    renderCategories();
  };
  try {
    if (silent) await run();
    else await withLoading(run);
  } catch (error) {
    $('categorySelect').innerHTML = '';
    $('categorySelect').append(new Option('No se pudieron cargar las categorías', ''));
    toast(error.message || 'No se pudieron cargar las categorías.', 'error');
  }
}

function renderCategories() {
  const select = $('categorySelect');
  select.innerHTML = '';
  select.append(new Option('Selecciona una categoría', ''));
  for (const category of state.categories) {
    select.append(new Option(safe(category.name), String(category.id)));
  }
  if (!state.categories.length) select.append(new Option('No hay categorías disponibles', ''));
}

function handleCategoryChange() {
  const category = state.categories.find((item) => String(item.id) === $('categorySelect').value);
  const hint = $('categoryHint');
  if (!category) {
    hint.hidden = true;
    return;
  }
  const parts = [];
  if (category.specialty_required) parts.push(`Especialidad: ${category.specialty_required}`);
  if (category.default_priority) parts.push(`Prioridad sugerida: ${category.default_priority}`);
  if (category.sla_resolution_hours) parts.push(`Objetivo: ${category.sla_resolution_hours} h`);
  hint.textContent = parts.join(' · ');
  hint.hidden = parts.length === 0;
}

function buildIncidentFormData(payload, photo) {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') form.append(key, String(value));
  });
  if (photo?.blob) form.append('before_photo', uploadFile(photo.blob, photoName(photo.blob)));
  return form;
}

async function queueReport(record) {
  try {
    await outboxPut(record);
    await updatePendingBadge();
    toast('Sin conexión: reporte pendiente de sincronización.', 'info', 6000);
  } catch {
    toast('No se pudo guardar el reporte sin conexión.', 'error');
  }
}

async function handleReportSubmit(event) {
  event.preventDefault();
  $('reportError').hidden = true;
  if (!validateForm($('reportForm'))) {
    $('reportError').textContent = 'Completa los campos obligatorios.';
    $('reportError').hidden = false;
    return;
  }
  let coords;
  try {
    coords = readCoords();
  } catch (error) {
    $('reportError').textContent = error.message;
    $('reportError').hidden = false;
    return;
  }
  const payload = {
    title: $('reportTitle').value.trim(),
    description: $('reportDescription').value.trim(),
    category_id: $('categorySelect').value,
    latitude: coords.latitude,
    longitude: coords.longitude,
    address_reference: $('addressReference').value.trim(),
    priority: $('prioritySelect').value
  };
  const idempotencyKey = uuid();
  const photo = state.reportPhoto;
  let photoDataUrl = null;
  try {
    if (photo?.blob) photoDataUrl = await withLoading(() => blobToDataUrl(photo.blob));
    await withLoading(() => api('/incidents', {
      method: 'POST',
      body: buildIncidentFormData(payload, photo),
      headers: { 'X-Idempotency-Key': idempotencyKey }
    }));
    toast('Reporte enviado correctamente.', 'success');
    $('reportForm').reset();
    setPreview('report', null);
    $('categoryHint').hidden = true;
    await loadIncidents(true);
    switchView('reports');
  } catch (error) {
    if ((error.network || !navigator.onLine) && error.status !== 401) {
      await queueReport({
        idempotencyKey,
        path: '/incidents',
        method: 'POST',
        payload,
        photoDataUrl,
        photoName: photo ? photoName(photo.blob) : null,
        photoType: photo?.blob?.type || null,
        photoField: 'before_photo',
        status: 'pending',
        createdAt: new Date().toISOString(),
        attempts: 0,
        error: null
      });
      $('reportForm').reset();
      setPreview('report', null);
      $('categoryHint').hidden = true;
      return;
    }
    $('reportError').textContent = error.message || 'No se pudo enviar el reporte.';
    $('reportError').hidden = false;
    toast(error.message || 'No se pudo enviar el reporte.', 'error');
  }
}

async function syncOutbox() {
  if (state.syncing || !navigator.onLine || !state.token || !state.user) return;
  state.syncing = true;
  try {
    const rows = await outboxAll();
    rows.sort((a, b) => String(a.createdAt || '').localeCompare(String(b.createdAt || '')));
    let synced = 0;
    for (const row of rows) {
      if (row.status !== 'pending' && row.status !== 'failed') continue;
      try {
        const form = new FormData();
        Object.entries(row.payload || {}).forEach(([key, value]) => {
          if (value !== null && value !== undefined && value !== '') form.append(key, String(value));
        });
        if (row.photoDataUrl) {
          const blob = dataUrlToBlob(row.photoDataUrl);
          const name = row.photoName || (row.photoType === 'image/webp' ? 'photo.webp' : 'photo.jpg');
          form.append(row.photoField || 'before_photo', uploadFile(blob, name));
        }
        await api(row.path || '/incidents', {
          method: row.method || 'POST',
          body: form,
          headers: { 'X-Idempotency-Key': row.idempotencyKey }
        });
        await outboxDelete(row.idempotencyKey);
        synced += 1;
      } catch (error) {
        if (error.status === 401) break;
        row.status = 'failed';
        row.error = error.message || 'Error desconocido';
        row.attempts = Number(row.attempts || 0) + 1;
        try {
          await outboxPut(row);
        } catch {
          break;
        }
        if (error.network || !navigator.onLine) break;
      }
    }
    if (synced > 0) {
      toast(`${synced} reporte(s) sincronizado(s).`, 'success');
      await loadIncidents(true).catch(() => {});
    }
    await updatePendingBadge();
  } catch {
    await updatePendingBadge().catch(() => {});
  } finally {
    state.syncing = false;
  }
}

function statusPill(value, kind = 'status') {
  const pill = el('span', 'pill', humanize(value));
  pill.dataset[kind] = String(value || '');
  return pill;
}

function renderReports() {
  const list = $('reportsList');
  const empty = $('reportsEmpty');
  list.innerHTML = '';
  if (!state.incidents.length) {
    empty.hidden = false;
    return;
  }
  empty.hidden = true;
  for (const incident of state.incidents) {
    const item = el('button', 'list-item');
    item.type = 'button';
    item.dataset.id = String(incident.id ?? incident.uuid ?? '');
    const top = el('div', 'row-between');
    top.append(statusPill(incident.status || 'PENDIENTE'), statusPill(incident.priority || 'MEDIA', 'priority'));
    const title = el('strong', null, safe(incident.title, 'Incidente'));
    const meta = el('small', null, [formatDate(incident.created_at), safe(incident.address_reference, '')].filter(Boolean).join(' · '));
    item.append(top, title, meta);
    item.addEventListener('click', () => openDetail(item.dataset.id));
    list.append(item);
  }
}

async function loadIncidents(silent = false) {
  const run = async () => {
    const response = await api('/incidents');
    state.incidents = asArray(response.data);
    if (state.activeView === 'reports' && $('reportDetail').hidden) renderReports();
    if (state.activeView === 'map') updateMap();
  };
  try {
    if (silent) await run();
    else await withLoading(run);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudieron cargar los reportes.', 'error');
  }
}

function closeDetail() {
  $('reportDetail').hidden = true;
  $('reportsList').hidden = false;
  state.detail = null;
  renderReports();
}

function setDetailPhoto(image, mediaItem) {
  if (mediaItem?.url) {
    image.src = mediaItem.url;
    image.hidden = false;
  } else {
    image.removeAttribute('src');
    image.hidden = true;
  }
}

function findMedia(media, pattern) {
  return asArray(media).find((item) => pattern.test(String(item?.collection_name || '')));
}

function renderDetail(incident) {
  state.detail = incident;
  $('detailTitle').textContent = safe(incident.title, 'Incidente');
  $('detailStatus').className = 'pill';
  $('detailStatus').dataset.status = String(incident.status || '');
  $('detailStatus').textContent = humanize(incident.status);
  $('detailPriority').className = 'pill';
  $('detailPriority').dataset.priority = String(incident.priority || '');
  $('detailPriority').textContent = humanize(incident.priority);
  const details = [formatDate(incident.created_at), safe(incident.address_reference, ''), `Lat ${safe(incident.latitude)} / Lng ${safe(incident.longitude)}`].filter(Boolean);
  $('detailMeta').textContent = details.join(' · ');
  $('detailDescription').textContent = safe(incident.description, 'Sin descripción.');
  const media = incident.media || [];
  setDetailPhoto($('detailBefore'), findMedia(media, /before|antes/i));
  setDetailPhoto($('detailAfter'), findMedia(media, /after|despues|resolved/i));

  const timeline = $('detailTimeline');
  timeline.innerHTML = '';
  const events = asArray(incident.timeline);
  if (!events.length) {
    timeline.append(el('li', null, 'Sin eventos registrados.'));
  } else {
    for (const event of events) {
      const item = el('li');
      const label = safe(event.event || event.action || event.description || event.status || event.type, 'Evento');
      item.append(document.createTextNode(label));
      if (event.notes || event.comment || event.reason) {
        item.append(el('span', 'muted', ` · ${safe(event.notes || event.comment || event.reason)}`));
      }
      const time = el('time', null, formatDate(event.created_at || event.at || event.timestamp));
      if (time.textContent) item.append(time);
      timeline.append(item);
    }
  }

  $('crewActions').hidden = !isCrew();
  $('rejectBtn').hidden = !isSupervisorAction();
  const status = String(incident.status || '').toUpperCase();
  const closed = ['RESUELTO', 'RECHAZADO', 'CANCELADO'].includes(status);
  $('startBtn').disabled = closed;
  $('resolveBtn').disabled = closed;
  $('rejectBtn').disabled = closed;
  $('afterPhotoInput').value = '';
  setPreview('after', null);
}

const isSupervisorAction = () => isStaff();

async function openDetail(id) {
  if (!id) return;
  $('reportDetail').hidden = false;
  $('reportsList').hidden = true;
  $('reportsEmpty').hidden = true;
  try {
    const response = await withLoading(() => api(`/incidents/${encodeURIComponent(id)}`));
    renderDetail(response.data || {});
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudo cargar el detalle.', 'error');
    closeDetail();
  }
}

async function refreshDetail(id) {
  const response = await api(`/incidents/${encodeURIComponent(id)}`);
  renderDetail(response.data || {});
}

async function handleStart() {
  const incident = state.detail;
  if (!incident) return;
  const id = incident.id ?? incident.uuid;
  try {
    const coords = await actionCoordinates(incident);
    await withLoading(() => api(`/incidents/${encodeURIComponent(id)}/start`, { method: 'POST', json: coords }));
    toast('Incidente iniciado.', 'success');
    await refreshDetail(id);
    await loadIncidents(true);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudo iniciar el incidente.', 'error');
  }
}

async function handleResolve() {
  const incident = state.detail;
  if (!incident) return;
  const id = incident.id ?? incident.uuid;
  if (!state.afterPhoto?.blob) {
    toast('Selecciona la foto posterior para resolver.', 'error');
    return;
  }
  try {
    const coords = await actionCoordinates(incident);
    const form = new FormData();
    form.append('latitude', String(coords.latitude));
    form.append('longitude', String(coords.longitude));
    form.append('after_photo', uploadFile(state.afterPhoto.blob, photoName(state.afterPhoto.blob)));
    await withLoading(() => api(`/incidents/${encodeURIComponent(id)}/resolve`, { method: 'POST', body: form }));
    toast('Incidente resuelto.', 'success');
    await refreshDetail(id);
    await loadIncidents(true);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudo resolver el incidente.', 'error');
  }
}

async function handleReject() {
  const incident = state.detail;
  if (!incident) return;
  const id = incident.id ?? incident.uuid;
  const reason = window.prompt('Motivo del rechazo');
  if (!reason || !reason.trim()) return;
  try {
    await withLoading(() => api(`/incidents/${encodeURIComponent(id)}/reject`, {
      method: 'POST',
      json: { reason: reason.trim() }
    }));
    toast('Incidente rechazado.', 'success');
    await refreshDetail(id);
    await loadIncidents(true);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudo rechazar el incidente.', 'error');
  }
}

let leafletPromise = null;

function loadLeaflet() {
  if (globalThis.L) return Promise.resolve(globalThis.L);
  if (leafletPromise) return leafletPromise;
  leafletPromise = new Promise((resolve, reject) => {
    if (!document.querySelector('link[data-civicfix-leaflet]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = LEAFLET_CSS;
      link.dataset.civicfixLeaflet = 'true';
      document.head.append(link);
    }
    const script = document.createElement('script');
    script.src = LEAFLET_JS;
    script.async = true;
    script.onload = () => (globalThis.L ? resolve(globalThis.L) : reject(new Error('Leaflet no disponible.')));
    script.onerror = () => reject(new Error('Leaflet no disponible.'));
    document.head.append(script);
  }).catch((error) => {
    leafletPromise = null;
    throw error;
  });
  return leafletPromise;
}

async function initMap() {
  if (state.map) {
    updateMap();
    return;
  }
  try {
    await withLoading(() => loadLeaflet());
  } catch {
    $('mapFallback').hidden = false;
    $('map').hidden = true;
    toast('El mapa no está disponible en este momento.', 'error');
    return;
  }
  $('map').hidden = false;
  $('mapFallback').hidden = true;
  state.map = L.map('map', { zoomControl: true }).setView([-12.0464, -77.0428], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(state.map);
  state.markerLayer = L.layerGroup().addTo(state.map);
  updateMap();
  window.setTimeout(() => state.map?.invalidateSize(), 120);
}

function priorityColor(priority) {
  return PRIORITY_COLORS[String(priority || '').toUpperCase()] || '#64748b';
}

function popupContent(incident) {
  const node = el('div');
  node.append(el('strong', null, safe(incident.title, 'Incidente')));
  node.append(el('br'));
  node.append(document.createTextNode(`${humanize(incident.status)} · ${humanize(incident.priority)}`));
  if (incident.address_reference) {
    node.append(el('br'));
    node.append(document.createTextNode(String(incident.address_reference)));
  }
  return node;
}

function updateMap() {
  if (!state.map || !state.markerLayer || !globalThis.L) return;
  state.markerLayer.clearLayers();
  const points = [];
  for (const incident of state.incidents) {
    const latitude = Number(incident.latitude);
    const longitude = Number(incident.longitude);
    if (Number.isFinite(latitude) && Number.isFinite(longitude)) points.push({ latitude, longitude, incident });
  }
  if (isStaff() && state.dashboard) {
    for (const point of asArray(state.dashboard.heatmap)) {
      const latitude = Number(point.latitude);
      const longitude = Number(point.longitude);
      if (Number.isFinite(latitude) && Number.isFinite(longitude)) points.push({ latitude, longitude, incident: point });
    }
  }
  for (const point of points) {
    const marker = L.circleMarker([point.latitude, point.longitude], {
      radius: 9,
      color: '#ffffff',
      weight: 2,
      fillColor: priorityColor(point.incident?.priority),
      fillOpacity: 0.95
    });
    marker.bindPopup(popupContent(point.incident || {}));
    marker.addTo(state.markerLayer);
  }
  if (state.focusPoint) {
    state.map.setView([state.focusPoint.latitude, state.focusPoint.longitude], 14);
    state.focusPoint = null;
  } else if (points.length > 1) {
    state.map.fitBounds(L.latLngBounds(points.map((point) => [point.latitude, point.longitude])).pad(0.2));
  } else if (points.length === 1) {
    state.map.setView([points[0].latitude, points[0].longitude], 13);
  }
}

function focusMap(latitude, longitude) {
  state.focusPoint = { latitude, longitude };
  switchView('map');
  if (state.map) {
    state.map.setView([latitude, longitude], 14);
    state.focusPoint = null;
  }
}

async function loadDashboard(silent = false) {
  const run = async () => {
    const response = await api('/dashboard');
    state.dashboard = response.data || {};
    renderDashboard();
    if (state.activeView === 'map') updateMap();
  };
  try {
    if (silent) await run();
    else await withLoading(run);
    if (isStaff()) await loadCrews(true);
    if (isAdmin()) await loadAuditLogs(true);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudo cargar el panel.', 'error');
  }
}

async function loadCrews(silent = false) {
  if (!isStaff()) return;
  const run = async () => {
    const response = await api('/crews');
    const list = $('crewList');
    list.innerHTML = '';
    const crews = asArray(response.data);
    if (!crews.length) {
      list.append(el('p', 'muted', 'No hay cuadrillas registradas.'));
      return;
    }
    for (const crew of crews) {
      const item = el('div', 'list-item');
      item.append(el('strong', null, safe(crew.name || crew.user?.name || `Cuadrilla ${crew.id}`, 'Cuadrilla')));
      const members = Array.isArray(crew.members) ? crew.members.length : null;
      const meta = [crew.status && humanize(crew.status), crew.specialty, members !== null ? `${members} integrante(s)` : ''].filter(Boolean).join(' · ');
      if (meta) item.append(el('small', null, meta));
      list.append(item);
    }
  };
  try {
    if (silent) await run();
    else await withLoading(run);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudieron cargar las cuadrillas.', 'error');
  }
}

function summaryCard(label, value) {
  const card = el('div', 'summary-card');
  card.append(el('strong', null, safe(value)));
  card.append(el('span', null, label));
  return card;
}

function normalizeCounts(value) {
  if (Array.isArray(value)) {
    const result = {};
    for (const entry of value) {
      const key = entry.status || entry.priority || entry.name || entry.key;
      if (key) result[key] = Number(entry.count ?? entry.total ?? 0);
    }
    return result;
  }
  return value && typeof value === 'object' ? value : {};
}

function renderDashboard() {
  const data = state.dashboard || {};
  const summary = data.summary || {};
  const byStatus = normalizeCounts(summary.by_status);
  const byPriority = normalizeCounts(summary.by_priority);
  const cards = $('summaryCards');
  cards.innerHTML = '';
  cards.append(
    summaryCard('Total', safe(summary.total, 0)),
    summaryCard('En proceso', byStatus.EN_PROCESO ?? byStatus.INICIADO ?? 0),
    summaryCard('Resueltos', byStatus.RESUELTO ?? 0),
    summaryCard('Prioridad alta', Number(byPriority.ALTA || 0) + Number(byPriority.CRITICA || 0)),
    summaryCard('Respuesta media (min)', safe(summary.avg_response_minutes, 0))
  );

  const chart = $('statusChart');
  chart.innerHTML = '';
  const entries = Object.entries(byStatus).sort((a, b) => Number(b[1]) - Number(a[1]));
  const max = Math.max(1, ...entries.map(([, count]) => Number(count) || 0));
  if (!entries.length) chart.append(el('p', 'muted', 'Sin datos por estado.'));
  for (const [status, count] of entries) {
    const row = el('div', 'bar-row');
    const track = el('div', 'bar-track');
    const fill = el('div', 'bar-fill');
    fill.style.width = `${Math.max(2, (Number(count) / max) * 100)}%`;
    fill.style.background = status === 'RESUELTO' ? '#22c55e' : status === 'RECHAZADO' ? '#ef4444' : '#2563eb';
    track.append(fill);
    row.append(el('span', null, humanize(status)), track, el('strong', null, safe(count, 0)));
    chart.append(row);
  }

  const heatmap = $('heatmapList');
  heatmap.innerHTML = '';
  const points = asArray(data.heatmap);
  if (!points.length) heatmap.append(el('p', 'muted', 'Sin puntos en el mapa.'));
  for (const point of points) {
    const item = el('button', 'list-item');
    item.type = 'button';
    const top = el('div', 'row-between');
    top.append(statusPill(point.priority || 'MEDIA', 'priority'), statusPill(point.status || 'PENDIENTE'));
    item.append(top, el('strong', null, safe(point.title, 'Incidente')));
    item.append(el('small', null, `${safe(point.latitude)} / ${safe(point.longitude)}`));
    const latitude = Number(point.latitude);
    const longitude = Number(point.longitude);
    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
      item.addEventListener('click', () => focusMap(latitude, longitude));
    }
    heatmap.append(item);
  }
}

async function loadAuditLogs(silent = false) {
  if (!isAdmin()) return;
  const run = async () => {
    const response = await api('/audit-logs');
    const list = $('auditList');
    list.innerHTML = '';
    const logs = asArray(response.data);
    if (!logs.length) {
      list.append(el('p', 'muted', 'Sin registros de auditoría.'));
      return;
    }
    for (const log of logs) {
      const item = el('div', 'list-item');
      item.append(el('strong', null, safe(log.action || log.event || log.description, 'Acción')));
      const actor = log.user?.name || log.user_name || log.actor || '';
      item.append(el('small', null, [actor, formatDate(log.created_at)].filter(Boolean).join(' · ')));
      list.append(item);
    }
  };
  try {
    if (silent) await run();
    else await withLoading(run);
  } catch (error) {
    if (error.status !== 401) toast(error.message || 'No se pudieron cargar los registros.', 'error');
  }
}

function updateConnection() {
  $('offlineBanner').hidden = navigator.onLine;
  if (navigator.onLine && state.user) syncOutbox();
}

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;
  const register = () => {
    navigator.serviceWorker.register('/pwa/sw.js', { scope: '/pwa/' }).catch(() => {});
  };
  if (document.readyState === 'complete') register();
  else window.addEventListener('load', register, { once: true });
}

let pullStartY = null;

function bindEvents() {
  $('tabLogin').addEventListener('click', () => setAuthMode('login'));
  $('tabRegister').addEventListener('click', () => setAuthMode('register'));
  $('authForm').addEventListener('submit', handleAuthSubmit);
  $('logoutBtn').addEventListener('click', handleLogout);
  $('categorySelect').addEventListener('change', handleCategoryChange);
  $('reportForm').addEventListener('submit', handleReportSubmit);
  $('locateBtn').addEventListener('click', startLocationWatch);
  $('stopLocateBtn').addEventListener('click', stopLocationWatch);
  $('cameraInput').addEventListener('change', (event) => handlePhotoPick(event.target, 'report'));
  $('galleryInput').addEventListener('change', (event) => handlePhotoPick(event.target, 'report'));
  $('removePhotoBtn').addEventListener('click', () => {
    setPreview('report', null);
    $('cameraInput').value = '';
    $('galleryInput').value = '';
  });
  $('afterPhotoInput').addEventListener('change', (event) => handlePhotoPick(event.target, 'after'));
  $('refreshReports').addEventListener('click', () => loadIncidents());
  $('detailBack').addEventListener('click', closeDetail);
  $('startBtn').addEventListener('click', handleStart);
  $('resolveBtn').addEventListener('click', handleResolve);
  $('rejectBtn').addEventListener('click', handleReject);
  $('tabbar').addEventListener('click', (event) => {
    const tab = event.target.closest('.tab');
    if (tab?.dataset.view) switchView(tab.dataset.view);
  });
  $('reportsList').addEventListener('touchstart', (event) => {
    pullStartY = window.scrollY <= 0 ? event.touches[0].clientY : null;
  }, { passive: true });
  $('reportsList').addEventListener('touchend', (event) => {
    if (pullStartY === null) return;
    const distance = event.changedTouches[0].clientY - pullStartY;
    pullStartY = null;
    if (distance > 70 && window.scrollY <= 0) loadIncidents();
  }, { passive: true });
  window.addEventListener('online', updateConnection);
  window.addEventListener('offline', updateConnection);
}

async function init() {
  bindEvents();
  setAuthMode('login');
  updateConnection();
  if (state.token) await restoreSession();
  else showAuth();
  updatePendingBadge().catch(() => {});
  syncOutbox();
  window.setInterval(() => {
    if (navigator.onLine) syncOutbox();
  }, 60000);
  registerServiceWorker();
}

init();
