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
  return "https://nshn.ir/maps?origin=" + origin.lat + "," + origin.lng +
    "&destination=" + clinic.latitude + "," + clinic.longitude + "&type=drive";
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
    item.innerHTML =
      '<article class="discovery-card" data-clinic-id="" data-lat="" data-lng="">' +
      "<h3></h3>" +
      "<p data-meta></p>" +
      "<p data-distance></p>" +
      "<p data-claim></p>" +
      '<div class="discovery-card-actions">' +
      '<a class="button primary" data-directions-link rel="noopener noreferrer" target="_blank"></a>' +
      '<a class="button ghost" data-request-link></a>' +
      "</div></article>";

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
    const requestLink = item.querySelector("[data-request-link]");
    requestLink.href = root.getAttribute("data-request-url") || "/fa/login";
    requestLink.textContent = text(copy, "select_clinic");

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
  let activeNeighborhoodId = root.getAttribute("data-neighborhood-id") || select?.value || "";
  let requestSerial = 0;

  function currentOrigin() {
    return ephemeralOrigin || neighborhoodOrigin;
  }

  function applyMatches(payload) {
    matches = Array.isArray(payload.matches)
      ? payload.matches.filter((row) => row.outcome === "match" && row.latitude != null && row.longitude != null)
      : [];

    neighborhoodOrigin = payload.origin || neighborhoodOrigin;
    renderList(root, matches, copy, currentOrigin());
    applyOriginToLinks(root, currentOrigin());

  }

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
        setStatus(root, "");
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

  matches = [...root.querySelectorAll(".discovery-card")]
    .map((card) => ({
      clinic_id: card.dataset.clinicId,
      latitude: Number(card.dataset.lat),
      longitude: Number(card.dataset.lng),
      outcome: "match",
    }))
    .filter((row) => !Number.isNaN(row.latitude) && !Number.isNaN(row.longitude));
}

document.querySelectorAll("[data-discovery-root]").forEach(initRoot);
