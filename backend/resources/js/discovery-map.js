import maplibregl from "@neshan-maps-platform/maplibre-sdk";
import "@neshan-maps-platform/maplibre-sdk/style.css";

const GPS_KEYS = ["latitude", "longitude", "lat", "lng", "gps", "origin_lat", "origin_lng"];

function text(copy, key) {
  return copy && typeof copy[key] === "string" ? copy[key] : "";
}

function parseCopy(root) {
  const node = root.querySelector("[data-discovery-copy]");
  if (!node || !node.textContent) {
    return {};
  }
  try {
    return JSON.parse(node.textContent);
  } catch {
    return {};
  }
}

function directionsUrl(origin, clinic) {
  return `https://nshn.ir/maps?origin=${origin.lat},${origin.lng}&destination=${clinic.latitude},${clinic.longitude}&type=drive`;
}

function setStatus(root, message) {
  const node = root.querySelector("[data-discovery-status]");
  if (node) {
    node.textContent = message || "";
  }
}

function renderList(root, matches, copy, origin) {
  const list = root.querySelector("[data-discovery-list]");
  if (!list) {
    return;
  }
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
    item.innerHTML = `
      <article class="discovery-card" data-clinic-id="" data-lat="" data-lng="">
        <h3></h3>
        <p data-meta></p>
        <p data-distance></p>
        <p data-claim></p>
        <div class="discovery-card-actions">
          <a class="button primary" data-directions-link rel="noopener noreferrer" target="_blank"></a>
          <button type="button" class="button ghost" data-select-clinic></button>
        </div>
      </article>
    `;
    const card = item.querySelector(".discovery-card");
    card.dataset.clinicId = clinic.clinic_id;
    card.dataset.lat = String(clinic.latitude);
    card.dataset.lng = String(clinic.longitude);
    item.querySelector("h3").textContent = clinic.name;
    item.querySelector("[data-meta]").textContent = [clinic.city, clinic.area_code].filter(Boolean).join(" · ");
    item.querySelector("[data-distance]").textContent = text(copy, "distance").replace(":km", String(clinic.distance_km));
    item.querySelector("[data-claim]").textContent = text(copy, "not_available_claim");
    const link = item.querySelector("[data-directions-link]");
    link.href = clinic.directions_url || directionsUrl(origin, clinic);
    link.textContent = text(copy, "directions");
    item.querySelector("[data-select-clinic]").textContent = text(copy, "select_clinic");
    fragment.append(item);
  });
  list.append(fragment);
}

function applyOriginToLinks(root, origin) {
  root.querySelectorAll(".discovery-card").forEach((card) => {
    const link = card.querySelector("[data-directions-link]");
    const lat = Number(card.dataset.lat);
    const lng = Number(card.dataset.lng);
    if (!link || Number.isNaN(lat) || Number.isNaN(lng)) {
      return;
    }
    link.href = directionsUrl(origin, { latitude: lat, longitude: lng });
  });
}

function failClosed(root, mapNode, copy, reasonKey) {
  if (mapNode) {
    mapNode.classList.add("is-unavailable");
    mapNode.setAttribute("hidden", "hidden");
    mapNode.setAttribute("aria-hidden", "true");
  }
  setStatus(root, text(copy, reasonKey) || text(copy, "map_unavailable"));
}

function mapSupported() {
  return typeof window.DecompressionStream === "function";
}

function createMap(mapNode, apiKey, origin) {
  const map = new maplibregl.Map({
    container: mapNode,
    style: "https://static.neshan.org/sdk/maplibre/styles/light.json",
    center: [origin.lng, origin.lat],
    zoom: 12,
    minZoom: 2,
    maxZoom: 21,
    trackResize: true,
    apiKey,
    rtl: true,
  });
  map.addControl(new maplibregl.NavigationControl());
  return map;
}

function plot(map, matches, selectedId, onSelect) {
  const markers = [];
  matches.forEach((clinic) => {
    const el = document.createElement("div");
    el.className = "discovery-marker" + (clinic.clinic_id === selectedId ? " is-selected" : "");
    el.title = clinic.name;
    const marker = new maplibregl.Marker({ element: el })
      .setLngLat([clinic.longitude, clinic.latitude])
      .setPopup(new maplibregl.Popup({ offset: 18 }).setText(clinic.name))
      .addTo(map);
    el.addEventListener("click", () => onSelect(clinic.clinic_id));
    markers.push(marker);
  });
  return markers;
}

function fit(map, origin, matches) {
  if (!matches.length) {
    map.jumpTo({ center: [origin.lng, origin.lat], zoom: 12 });
    return;
  }
  const bounds = new maplibregl.LngLatBounds();
  bounds.extend([origin.lng, origin.lat]);
  matches.forEach((clinic) => bounds.extend([clinic.longitude, clinic.latitude]));
  map.fitBounds(bounds, { padding: 48, maxZoom: 14, duration: 0 });
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
  if (!response.ok) {
    throw new Error(body?.error?.code || "discovery.failed");
  }
  return body.data;
}

function initRoot(root) {
  const copy = parseCopy(root);
  const endpoint = root.getAttribute("data-endpoint") || "";
  const serviceType = root.getAttribute("data-service-type") || "guidance_referral";
  const mapNode = root.querySelector("[data-discovery-map]");
  const geoBtn = root.querySelector("[data-discovery-geo]");
  const geoClear = root.querySelector("[data-discovery-geo-clear]");
  const select = root.querySelector("[data-discovery-neighborhood]");
  const form = root.querySelector("[data-discovery-form]");
  const apiKey = (root.getAttribute("data-api-key") || "").trim();

  let origin = {
    lat: Number(root.getAttribute("data-origin-lat")),
    lng: Number(root.getAttribute("data-origin-lng")),
  };
  let neighborhoodOrigin = { ...origin };
  let ephemeralOrigin = null;
  let matches = [];
  let selectedId = null;
  let map = null;
  let markers = [];
  let activeNeighborhoodId = root.getAttribute("data-neighborhood-id") || select?.value || "";
  let requestSerial = 0;

  function currentOrigin() {
    return ephemeralOrigin || neighborhoodOrigin;
  }

  function highlight(id) {
    selectedId = id;
    root.querySelectorAll(".discovery-card").forEach((card) => {
      card.classList.toggle("is-selected", card.dataset.clinicId === id);
    });
    const clinic = matches.find((row) => row.clinic_id === id);
    if (clinic && map) {
      map.easeTo({ center: [clinic.longitude, clinic.latitude], zoom: Math.max(map.getZoom(), 14) });
    }
  }

  function syncMarkers() {
    markers.forEach((marker) => marker.remove());
    markers = [];
    if (!map) {
      return;
    }
    markers = plot(map, matches, selectedId, highlight);
    fit(map, neighborhoodOrigin, matches);
  }

  function applyMatches(payload) {
    matches = Array.isArray(payload.matches) ? payload.matches.filter((row) => row.outcome === "match" && row.latitude != null && row.longitude != null) : [];
    neighborhoodOrigin = payload.origin || neighborhoodOrigin;
    origin = currentOrigin();
    renderList(root, matches, copy, origin);
    applyOriginToLinks(root, origin);
    if (selectedId && !matches.some((row) => row.clinic_id === selectedId)) {
      selectedId = null;
    }
    syncMarkers();
  }

  root.addEventListener("click", (event) => {
    const selectBtn = event.target.closest("[data-select-clinic]");
    const card = event.target.closest(".discovery-card");
    if (selectBtn && card) {
      highlight(card.dataset.clinicId);
    }
  });

  if (form && select) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const neighborhoodId = select.value;
      const serial = ++requestSerial;
      try {
        const payload = await loadMatches(endpoint, neighborhoodId, serviceType);
        if (serial !== requestSerial) {
          return;
        }
        applyMatches(payload);
        activeNeighborhoodId = payload.neighborhood_id || neighborhoodId;
        root.setAttribute("data-neighborhood-id", activeNeighborhoodId);
        select.value = activeNeighborhoodId;
      } catch {
        if (serial !== requestSerial) {
          return;
        }
        if (activeNeighborhoodId) {
          select.value = activeNeighborhoodId;
          root.setAttribute("data-neighborhood-id", activeNeighborhoodId);
        }
        setStatus(root, text(copy, "map_unavailable"));
      }
    });
    select.addEventListener("change", () => {
      form.requestSubmit();
    });
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
          if (geoClear) {
            geoClear.hidden = false;
          }
          setStatus(root, text(copy, "location_ephemeral"));
        },
        () => {
          ephemeralOrigin = null;
          applyOriginToLinks(root, neighborhoodOrigin);
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
      geoClear.hidden = true;
      setStatus(root, "");
    });
  }

  const initialCards = [...root.querySelectorAll(".discovery-card")];
  matches = initialCards.map((card) => ({
    clinic_id: card.dataset.clinicId,
    name: card.querySelector("h3")?.textContent || "",
    latitude: Number(card.dataset.lat),
    longitude: Number(card.dataset.lng),
    outcome: "match",
    directions_url: card.querySelector("[data-directions-link]")?.href,
  })).filter((row) => !Number.isNaN(row.latitude) && !Number.isNaN(row.longitude));

  if (!apiKey || !mapNode || !mapSupported()) {
    failClosed(root, mapNode, copy, apiKey ? "map_unsupported" : "map_unavailable");
    return;
  }

  try {
    map = createMap(mapNode, apiKey, neighborhoodOrigin);
    map.on("error", () => failClosed(root, mapNode, copy, "map_unavailable"));
    map.on("load", () => syncMarkers());
  } catch {
    failClosed(root, mapNode, copy, "map_unavailable");
  }
}

document.querySelectorAll("[data-discovery-root]").forEach(initRoot);
