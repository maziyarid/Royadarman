import { LngLatBounds, Map, Marker, NavigationControl } from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";

const GPS_KEYS = ["latitude", "longitude", "lat", "lng", "gps", "origin_lat", "origin_lng"];
const SNAPP_URL = "https://snapp.ir/";
const TAPSI_URL = "https://tapsi.ir/";

function text(copy, key) {
  return copy && typeof copy[key] === "string" ? copy[key] : "";
}

function parseCopy(root) {
  const node = root.querySelector("[data-discovery-copy]");
  if (!node || !node.textContent) return {};
  try {
    return JSON.parse(node.textContent);
  } catch {
    return {};
  }
}

function neshanDirections(origin, clinic) {
  return "https://nshn.ir/maps?origin=" + encodeURIComponent(origin.lat + "," + origin.lng) +
    "&destination=" + encodeURIComponent(clinic.latitude + "," + clinic.longitude) + "&type=drive";
}

function googleDirections(clinic) {
  return "https://www.google.com/maps/dir/?api=1&destination=" +
    encodeURIComponent(clinic.latitude + "," + clinic.longitude) + "&travelmode=driving";
}

function wazeDirections(clinic) {
  return "https://waze.com/ul?ll=" +
    encodeURIComponent(clinic.latitude + "," + clinic.longitude) +
    "&navigate=yes&utm_source=royadarman";
}

function baladLocation(clinic) {
  return "https://balad.ir/location?latitude=" +
    encodeURIComponent(clinic.latitude) + "&longitude=" + encodeURIComponent(clinic.longitude);
}

function osmLocation(clinic) {
  return "https://www.openstreetmap.org/?mlat=" +
    encodeURIComponent(clinic.latitude) + "&mlon=" + encodeURIComponent(clinic.longitude) +
    "#map=17/" + encodeURIComponent(clinic.latitude) + "/" + encodeURIComponent(clinic.longitude);
}

function setStatus(root, message) {
  const node = root.querySelector("[data-discovery-status]");
  if (node) node.textContent = message || "";
}

function externalLink(label, href, className = "button ghost") {
  const a = document.createElement("a");
  a.className = className;
  a.href = href;
  a.target = "_blank";
  a.rel = "noopener noreferrer";
  a.textContent = label;
  return a;
}

function appendNavigation(container, clinic, origin, copy) {
  const wrap = document.createElement("div");
  wrap.className = "discovery-navigation";

  const title = document.createElement("strong");
  title.className = "discovery-navigation-title";
  title.textContent = text(copy, "navigation_title");
  wrap.append(title);

  const links = document.createElement("div");
  links.className = "discovery-navigation-links";

  const nav = clinic.navigation || {};
  links.append(
    externalLink(text(copy, "nav_neshan"), nav.neshan || neshanDirections(origin, clinic)),
    externalLink(text(copy, "nav_balad"), nav.balad || baladLocation(clinic)),
    externalLink(text(copy, "nav_google"), nav.google || googleDirections(clinic)),
    externalLink(text(copy, "nav_waze"), nav.waze || wazeDirections(clinic)),
    externalLink(text(copy, "nav_osm"), nav.osm || osmLocation(clinic)),
  );

  [
    [text(copy, "nav_snapp"), SNAPP_URL],
    [text(copy, "nav_tapsi"), TAPSI_URL],
  ].forEach(([label, href]) => {
    const link = externalLink(label, href);
    link.dataset.copyDestination = clinic.latitude + "," + clinic.longitude;
    links.append(link);
  });

  wrap.append(links);
  container.append(wrap);
}

function renderList(root, matches, copy, origin) {
  const list = root.querySelector("[data-discovery-list]");
  if (!list) return;

  list.replaceChildren();

  if (!matches.length) {
    const empty = document.createElement("li");
    empty.className = "discovery-empty";
    empty.setAttribute("data-discovery-empty", "");
    empty.textContent = text(copy, "empty");
    list.append(empty);
    return;
  }

  const fragment = document.createDocumentFragment();

  matches.forEach((clinic) => {
    const item = document.createElement("li");
    const card = document.createElement("article");
    card.className = "discovery-card";
    card.dataset.clinicId = clinic.clinic_id;
    card.dataset.lat = String(clinic.latitude);
    card.dataset.lng = String(clinic.longitude);

    const heading = document.createElement("h3");
    heading.textContent = clinic.name;

    const meta = document.createElement("p");
    meta.textContent = [clinic.city, clinic.area_code].filter(Boolean).join(" · ");

    const distance = document.createElement("p");
    distance.textContent = text(copy, "distance").replace(":km", String(clinic.distance_km));

    const claim = document.createElement("p");
    claim.textContent = text(copy, "not_available_claim");

    const actions = document.createElement("div");
    actions.className = "discovery-card-actions";

    const primary = externalLink(
      text(copy, "directions"),
      clinic.navigation?.neshan || clinic.directions_url || neshanDirections(origin, clinic),
      "button primary",
    );
    primary.dataset.directionsLink = "";

    const select = document.createElement("button");
    select.type = "button";
    select.className = "button ghost";
    select.dataset.selectClinic = "";
    select.setAttribute("aria-pressed", "false");
    select.textContent = text(copy, "select_clinic");

    actions.append(primary, select);
    card.append(heading, meta, distance, claim, actions);
    appendNavigation(card, clinic, origin, copy);
    item.append(card);
    fragment.append(item);
  });

  list.append(fragment);
}

function applyOriginToLinks(root, origin) {
  root.querySelectorAll(".discovery-card").forEach((card) => {
    const link = card.querySelector("[data-directions-link]");
    const lat = Number(card.dataset.lat);
    const lng = Number(card.dataset.lng);
    if (!link || Number.isNaN(lat) || Number.isNaN(lng)) return;
    link.href = neshanDirections(origin, { latitude: lat, longitude: lng });
  });
}

async function copyDestination(root, value, copy) {
  try {
    await navigator.clipboard.writeText(value);
    setStatus(root, text(copy, "destination_copied"));
  } catch {
    const fallback = document.createElement("textarea");
    fallback.value = value;
    fallback.setAttribute("readonly", "");
    fallback.style.position = "fixed";
    fallback.style.opacity = "0";
    document.body.append(fallback);
    fallback.select();
    document.execCommand("copy");
    fallback.remove();
    setStatus(root, text(copy, "destination_copied"));
  }
}

async function loadMatches(endpoint, neighborhoodId, serviceType) {
  const url = new URL(endpoint, window.location.origin);
  url.searchParams.set("neighborhood_id", neighborhoodId);
  url.searchParams.set("service_type", serviceType);
  GPS_KEYS.forEach((key) => url.searchParams.delete(key));

  const response = await fetch(url.toString(), {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  const body = await response.json();

  if (!response.ok) throw new Error(body?.error?.code || "discovery.failed");
  return body.data;
}

function createMap(root, onSelect) {
  const container = root.querySelector("[data-discovery-map]");
  const tileUrl = root.dataset.tileUrl;
  if (!container || !tileUrl || typeof WebGLRenderingContext === "undefined") return null;

  try {
    const map = new Map({
      container,
      center: [Number(root.dataset.originLng), Number(root.dataset.originLat)],
      zoom: 12,
      style: {
        version: 8,
        sources: {
          osm: {
            type: "raster",
            tiles: [tileUrl],
            tileSize: 256,
            attribution: "© OpenStreetMap contributors",
          },
        },
        layers: [{ id: "osm", type: "raster", source: "osm" }],
      },
      attributionControl: true,
    });

    map.addControl(new NavigationControl({ visualizePitch: false }), "top-left");
    map.on("error", () => {
      container.classList.add("has-map-error");
    });

    return { map, markers: new Map(), userMarker: null, onSelect };
  } catch {
    return null;
  }
}

function updateMap(state, root, matches, origin) {
  if (!state) return;

  state.markers.forEach((marker) => marker.remove());
  state.markers.clear();

  const bounds = new LngLatBounds();
  bounds.extend([origin.lng, origin.lat]);

  matches.forEach((clinic) => {
    const lat = Number(clinic.latitude);
    const lng = Number(clinic.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    const el = document.createElement("button");
    el.type = "button";
    el.className = "discovery-marker";
    el.setAttribute("aria-label", clinic.name || "");
    el.addEventListener("click", () => state.onSelect(clinic.clinic_id));

    const marker = new Marker({ element: el })
      .setLngLat([lng, lat])
      .addTo(state.map);

    state.markers.set(clinic.clinic_id, { marker, element: el });
    bounds.extend([lng, lat]);
  });

  if (!bounds.isEmpty()) {
    state.map.fitBounds(bounds, { padding: 54, maxZoom: 14, duration: 450 });
  }
}

function updateEphemeralUserMarker(state, origin) {
  if (!state) return;
  if (state.userMarker) state.userMarker.remove();

  if (!origin) {
    state.userMarker = null;
    return;
  }

  const element = document.createElement("div");
  element.className = "discovery-user-marker";
  state.userMarker = new Marker({ element })
    .setLngLat([origin.lng, origin.lat])
    .addTo(state.map);
}

function initRoot(root) {
  const copy = parseCopy(root);
  const endpoint = root.getAttribute("data-endpoint") || "";
  const serviceType = root.getAttribute("data-service-type") || "guidance_referral";
  const geoBtn = root.querySelector("[data-discovery-geo]");
  const geoClear = root.querySelector("[data-discovery-geo-clear]");
  const select = root.querySelector("[data-discovery-neighborhood]");
  const form = root.querySelector("[data-discovery-form]");

  let neighborhoodOrigin = {
    lat: Number(root.getAttribute("data-origin-lat")),
    lng: Number(root.getAttribute("data-origin-lng")),
  };
  let ephemeralOrigin = null;
  let matches = [];
  let selectedId = null;
  let activeNeighborhoodId = root.getAttribute("data-neighborhood-id") || select?.value || "";
  let requestSerial = 0;
  let mapState = null;

  function currentOrigin() {
    return ephemeralOrigin || neighborhoodOrigin;
  }

  function highlight(id) {
    selectedId = id;
    root.querySelectorAll(".discovery-card").forEach((card) => {
      const isSelected = card.dataset.clinicId === id;
      card.classList.toggle("is-selected", isSelected);
      const button = card.querySelector("[data-select-clinic]");
      if (button) {
        button.setAttribute("aria-pressed", isSelected ? "true" : "false");
        button.textContent = text(copy, isSelected ? "clinic_selected" : "select_clinic");
      }
      if (isSelected) card.setAttribute("aria-current", "true");
      else card.removeAttribute("aria-current");
    });

    mapState?.markers.forEach(({ element }, markerId) => {
      element.classList.toggle("is-selected", markerId === id);
    });

    const selected = matches.find((row) => row.clinic_id === id);
    if (selected) setStatus(root, text(copy, "clinic_selected_status").replace(":clinic", selected.name || ""));
    if (selected && mapState) {
      mapState.map.easeTo({
        center: [Number(selected.longitude), Number(selected.latitude)],
        zoom: Math.max(mapState.map.getZoom(), 14),
      });
    }
  }

  mapState = createMap(root, highlight);

  function applyMatches(payload) {
    matches = Array.isArray(payload.matches)
      ? payload.matches.filter((row) => row.outcome === "match" && row.latitude != null && row.longitude != null)
      : [];

    neighborhoodOrigin = payload.origin || neighborhoodOrigin;
    renderList(root, matches, copy, currentOrigin());
    applyOriginToLinks(root, currentOrigin());
    updateMap(mapState, root, matches, neighborhoodOrigin);

    if (selectedId && !matches.some((row) => row.clinic_id === selectedId)) selectedId = null;
  }

  root.addEventListener("click", (event) => {
    const copyLink = event.target.closest("[data-copy-destination]");
    if (copyLink) {
      void copyDestination(root, copyLink.dataset.copyDestination || "", copy);
    }

    const selectBtn = event.target.closest("[data-select-clinic]");
    const card = event.target.closest(".discovery-card");
    if (selectBtn && card) highlight(card.dataset.clinicId);
  });

  if (form && select) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const neighborhoodId = select.value;
      const serial = ++requestSerial;

      try {
        const payload = await loadMatches(endpoint, neighborhoodId, serviceType);
        if (serial !== requestSerial) return;

        applyMatches(payload);
        activeNeighborhoodId = payload.neighborhood_id || neighborhoodId;
        root.setAttribute("data-neighborhood-id", activeNeighborhoodId);
        select.value = activeNeighborhoodId;
        setStatus(root, "");
      } catch {
        if (serial !== requestSerial) return;
        if (activeNeighborhoodId) {
          select.value = activeNeighborhoodId;
          root.setAttribute("data-neighborhood-id", activeNeighborhoodId);
        }
        setStatus(root, text(copy, "map_unavailable"));
      }
    });

    select.addEventListener("change", () => form.requestSubmit());
  }

  if (geoBtn && navigator.geolocation) {
    geoBtn.hidden = false;
    geoBtn.addEventListener("click", () => {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          ephemeralOrigin = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          };
          applyOriginToLinks(root, currentOrigin());
          updateEphemeralUserMarker(mapState, ephemeralOrigin);
          if (geoClear) geoClear.hidden = false;
          setStatus(root, text(copy, "location_ephemeral"));
        },
        () => {
          ephemeralOrigin = null;
          applyOriginToLinks(root, neighborhoodOrigin);
          updateEphemeralUserMarker(mapState, null);
          setStatus(root, text(copy, "location_denied"));
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 0 },
      );
    });
  }

  if (geoClear) {
    geoClear.addEventListener("click", () => {
      ephemeralOrigin = null;
      applyOriginToLinks(root, neighborhoodOrigin);
      updateEphemeralUserMarker(mapState, null);
      geoClear.hidden = true;
      setStatus(root, "");
    });
  }

  matches = [...root.querySelectorAll(".discovery-card")]
    .map((card) => ({
      clinic_id: card.dataset.clinicId,
      name: card.querySelector("h3")?.textContent || "",
      latitude: Number(card.dataset.lat),
      longitude: Number(card.dataset.lng),
      outcome: "match",
    }))
    .filter((row) => !Number.isNaN(row.latitude) && !Number.isNaN(row.longitude));

  updateMap(mapState, root, matches, neighborhoodOrigin);
}

document.querySelectorAll("[data-discovery-root]").forEach(initRoot);
