(function () {
  const root = document.getElementById('app-root');
  if (!root) return;

  const bootstrap = readBootstrap();
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const user = bootstrap.user ?? {};
  const page = bootstrap.page || pageFromPath(window.location.pathname);
  const state = {
    filters: readFilters(),
    incidentId: extractIncidentId(window.location.pathname),
  };

  const statusLabels = {
    new: 'Nowe',
    in_progress: 'W toku',
    resolved: 'Rozwiązane',
    rejected: 'Odrzucone',
  };

  const roleLabels = {
    citizen: 'Mieszkaniec',
    moderator: 'Moderator',
    admin: 'Administrator',
  };

  init().catch((error) => {
    renderPage(pageTitle(page), renderErrorPanel(error), { section: sectionForPage(page) });
  });

  async function init() {
    if (page === 'dashboard') return renderDashboard();
    if (page === 'incidents') return renderIncidentsFeed();
    if (page === 'report') return renderReportForm();
    if (page === 'incident-detail') return renderIncidentDetail();
    if (page === 'admin') return renderAdminCenter();

    renderPage(pageTitle(page), renderEmptyState('Ta trasa nie jest jeszcze obsłużona.', 'Wróć do panelu albo listy zgłoszeń.'));
  }

  async function renderDashboard() {
    renderPage(pageTitle(page), renderLoading('Ładowanie panelu...'), { section: 'dashboard' });

    const data = await requestJson('/api/dashboard/stats');
    const stats = data.stats || {};
    const recentIncidents = Array.isArray(data.recent_incidents) ? data.recent_incidents : [];
    const categoryBreakdown = Array.isArray(data.category_breakdown) ? data.category_breakdown : [];
    const recentActivity = Array.isArray(data.recent_activity) ? data.recent_activity : [];
    const usersByRole = Array.isArray(data.users_by_role) ? data.users_by_role : [];

    renderPage(pageTitle(page), `
      <section class="page-shell page-shell--dashboard">
        <div class="page-head page-head--dashboard">
          <div>
            <p class="page-eyebrow">Panel</p>
            <h1>Panel</h1>
            <p class="page-subtitle">Aktualna liczba zgłoszeń, ostatnie zmiany i przegląd ról w systemie.</p>
          </div>
          <div class="page-actions">
            <a class="ui-btn ui-btn--secondary" href="/incidents">Zobacz zgłoszenia</a>
            <a class="ui-btn ui-btn--primary" href="/incidents/report">Nowe zgłoszenie</a>
          </div>
        </div>

        <section class="metrics-grid metrics-grid--four">
          ${metricCard('Wszystkie zgłoszenia', stats.total ?? 0, 'rekordów w systemie', 'metric-card--blue')}
          ${metricCard('Nowe', stats.new_count ?? 0, 'czekają na weryfikację', 'metric-card--orange')}
          ${metricCard('W toku', stats.in_progress_count ?? 0, 'są właśnie obsługiwane', 'metric-card--sand')}
          ${metricCard('Rozwiązane', stats.resolved_count ?? 0, 'zamkniętych zgłoszeń', 'metric-card--green')}
        </section>

        <div class="dashboard-layout">
          <section class="surface-card surface-card--padded">
            <div class="card-head">
              <div>
                <p class="card-eyebrow">Ostatnie zgłoszenia</p>
                <h2>Bieżąca kolejka</h2>
              </div>
              <span class="card-meta">Pokazano ${escapeHtml(recentIncidents.length)} najnowszych pozycji</span>
            </div>
            ${recentIncidents.length ? renderIncidentTable(recentIncidents) : renderEmptyState('Brak zgłoszeń', 'Nie ma jeszcze pozycji w kolejce.')}
          </section>

          <aside class="stack-column">
            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Podsumowanie</p>
                  <h2>Przegląd kolejki</h2>
                </div>
                <a class="card-link" href="/incidents">Otwórz zgłoszenia</a>
              </div>
              <p class="support-copy">Pełny widok zgłoszeń pozwala wyszukiwać, filtrować i przeglądać pojedyncze sprawy.</p>
              <div class="pill-row pill-row--wrap">
                ${['new', 'in_progress', 'resolved', 'rejected'].map((key) => statusPill(key)).join('')}
              </div>
              <div class="mini-toolbar">
                <div class="mini-toolbar__item">
                  <span>Najczęstsza kategoria</span>
                  <strong>${escapeHtml(categoryBreakdown[0]?.category_name || 'Brak danych')}</strong>
                </div>
                <div class="mini-toolbar__item">
                  <span>Ostatnie zmiany</span>
                  <strong>${escapeHtml(recentActivity.length)}</strong>
                </div>
                <div class="mini-toolbar__item">
                  <span>Aktywne role</span>
                  <strong>${escapeHtml(usersByRole.length)}</strong>
                </div>
              </div>
              <a class="ui-btn ui-btn--secondary ui-btn--full" href="/incidents">Przejrzyj kolejkę</a>
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Skróty</p>
                  <h2>Następne działania</h2>
                </div>
              </div>
              <div class="stack-column stack-column--tight">
                <a class="ui-btn ui-btn--primary ui-btn--full" href="/incidents/report">Dodaj zgłoszenie</a>
                ${user.role === 'admin' ? '<a class="ui-btn ui-btn--secondary ui-btn--full" href="/admin">Zarządzaj dostępem</a>' : ''}
              </div>
            </section>
          </aside>
        </div>

        <div class="dashboard-layout dashboard-layout--secondary">
          <section class="surface-card surface-card--padded">
            <div class="card-head">
              <div>
                <p class="card-eyebrow">Przekrój</p>
                <h2>Zgłoszenia według kategorii</h2>
              </div>
            </div>
            ${categoryBreakdown.length ? renderCategoryDistribution(categoryBreakdown) : renderEmptyState('Brak danych', 'Nie ma jeszcze rozkładu kategorii.')}
          </section>

          <section class="surface-card surface-card--padded">
            <div class="card-head">
              <div>
                <p class="card-eyebrow">Trend</p>
                <h2>Aktywność</h2>
              </div>
              <span class="card-meta">Dzisiaj</span>
            </div>
            ${renderTrendChart(stats, recentIncidents)}
          </section>
        </div>

        ${(recentActivity.length || usersByRole.length) ? `
          <div class="dashboard-layout dashboard-layout--secondary">
            <section class="surface-card surface-card--padded">
              <div class="card-head">
                <div>
                  <p class="card-eyebrow">Ostatnia aktywność</p>
                  <h2>Zmiany statusów</h2>
                </div>
              </div>
              ${recentActivity.length ? renderTimeline(recentActivity, true) : renderEmptyState('Brak aktywności', 'Historia pojawi się po pierwszych zmianach.')}
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head">
                <div>
                  <p class="card-eyebrow">Użytkownicy</p>
                  <h2>Rozkład ról</h2>
                </div>
              </div>
              ${usersByRole.length ? renderMetricList(usersByRole, 'role_name', 'users_count', true) : renderEmptyState('Brak danych', 'Nie udało się pobrać statystyk ról.')}
            </section>
          </div>
        ` : ''}
      </section>
    `, { section: 'dashboard' });
  }

  async function renderIncidentsFeed() {
    renderPage(pageTitle(page), renderLoading('Ładowanie feedu...'), { section: 'incidents' });

    const query = buildIncidentQuery(state.filters);
    const data = await requestJson(`/api/incidents${query ? `?${query}` : ''}`);
    const incidents = Array.isArray(data.incidents) ? data.incidents : [];
    const meta = data.meta || {};
    const filters = data.filters || {};
    const categories = Array.isArray(meta.categories) ? meta.categories : [];
    const statuses = Array.isArray(meta.statuses) ? meta.statuses : [];
    const latestUpdatedAt = incidents[0]?.updated_at || incidents[0]?.created_at || null;

    state.filters = {
      status: filters.status ?? '',
      category_id: filters.category_id ? String(filters.category_id) : '',
      search: filters.search ?? '',
    };

    renderPage(pageTitle(page), `
      <section class="page-shell page-shell--reports">
        <div class="page-head page-head--reports">
          <div>
            <p class="page-eyebrow">Zgłoszenia</p>
            <h1>Zgłoszenia</h1>
            <p class="page-subtitle">Wyświetlono ${escapeHtml(incidents.length)} zgłoszeń pasujących do bieżących filtrów.</p>
          </div>
          <div class="page-actions page-actions--meta">
            <span class="page-meta">Zaktualizowano ${escapeHtml(relativeAge(latestUpdatedAt))}</span>
            <a class="ui-btn ui-btn--primary" href="/incidents/report">Nowe zgłoszenie</a>
          </div>
        </div>

        <form id="incident-filters" class="surface-card surface-card--padded reports-filters" novalidate>
          <input type="hidden" name="status" value="${escapeAttr(state.filters.status)}">
          <input type="hidden" name="category_id" value="${escapeAttr(state.filters.category_id)}">

          <div class="pill-row pill-row--wrap">
            <button class="filter-pill${!state.filters.status ? ' is-active' : ''}" type="button" data-status="">Wszystkie</button>
            ${statuses.map((status) => `
              <button class="filter-pill${state.filters.status === status.name ? ' is-active' : ''}" type="button" data-status="${escapeAttr(status.name)}">
                ${escapeHtml(statusLabels[status.name] || titleCase(status.name))}
              </button>
            `).join('')}
          </div>

          <div class="pill-row pill-row--wrap">
            <button class="filter-pill filter-pill--soft${!state.filters.category_id ? ' is-active' : ''}" type="button" data-category="">Wszystkie kategorie</button>
            ${categories.slice(0, 6).map((category) => `
              <button class="filter-pill filter-pill--soft${state.filters.category_id === String(category.id) ? ' is-active' : ''}" type="button" data-category="${escapeAttr(category.id)}">
                ${escapeHtml(category.name)}
              </button>
            `).join('')}
          </div>

          <div class="reports-toolbar">
            <label class="field field--light">
              <span>Szukaj</span>
              <input name="search" value="${escapeAttr(state.filters.search)}" placeholder="Tytuł, lokalizacja lub opis">
            </label>

            <label class="field field--light">
              <span>Status</span>
              <select name="status_select">
                <option value="">Wszystkie statusy</option>
                ${statuses.map((status) => `
                  <option value="${escapeAttr(status.name)}"${state.filters.status === status.name ? ' selected' : ''}>
                    ${escapeHtml(statusLabels[status.name] || titleCase(status.name))}
                  </option>
                `).join('')}
              </select>
            </label>

            <label class="field field--light">
              <span>Kategoria</span>
              <select name="category_select">
                <option value="">Wszystkie kategorie</option>
                ${categories.map((category) => `
                  <option value="${escapeAttr(category.id)}"${state.filters.category_id === String(category.id) ? ' selected' : ''}>
                    ${escapeHtml(category.name)}
                  </option>
                `).join('')}
              </select>
            </label>

            <div class="reports-toolbar__actions">
              <button class="ui-btn ui-btn--secondary" type="reset">Wyczyść</button>
              <button class="ui-btn ui-btn--primary" type="submit">Zastosuj</button>
            </div>
          </div>
        </form>

        <section class="report-card-grid">
          ${incidents.length ? incidents.map((incident) => incidentCard(incident)).join('') : renderEmptyState('Brak zgłoszeń', 'Nie znaleziono pozycji dla wybranych filtrów.')}
        </section>
      </section>
    `, { section: 'incidents' });

    const form = root.querySelector('#incident-filters');
    if (!form) return;

    const hiddenStatus = form.elements.status;
    const hiddenCategory = form.elements.category_id;
    const statusSelect = form.elements.status_select;
    const categorySelect = form.elements.category_select;

    statusSelect.addEventListener('change', () => {
      hiddenStatus.value = statusSelect.value;
    });

    categorySelect.addEventListener('change', () => {
      hiddenCategory.value = categorySelect.value;
    });

    form.querySelectorAll('[data-status]').forEach((button) => {
      button.addEventListener('click', () => {
        hiddenStatus.value = button.getAttribute('data-status') || '';
        statusSelect.value = hiddenStatus.value;
        form.requestSubmit();
      });
    });

    form.querySelectorAll('[data-category]').forEach((button) => {
      button.addEventListener('click', () => {
        hiddenCategory.value = button.getAttribute('data-category') || '';
        categorySelect.value = hiddenCategory.value;
        form.requestSubmit();
      });
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const nextFilters = {
        status: String(hiddenStatus.value || statusSelect.value || ''),
        category_id: String(hiddenCategory.value || categorySelect.value || ''),
        search: String(form.elements.search.value || '').trim(),
      };

      state.filters = nextFilters;
      const nextQuery = buildIncidentQuery(nextFilters);
      window.history.replaceState({}, '', `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}`);
      renderIncidentsFeed().catch((error) => renderPage(pageTitle(page), renderErrorPanel(error), { section: 'incidents' }));
    });

    form.addEventListener('reset', () => {
      window.setTimeout(() => {
        state.filters = { status: '', category_id: '', search: '' };
        window.history.replaceState({}, '', window.location.pathname);
        renderIncidentsFeed().catch((error) => renderPage(pageTitle(page), renderErrorPanel(error), { section: 'incidents' }));
      }, 0);
    });
  }

  async function renderReportForm() {
    renderPage(pageTitle(page), renderLoading('Ładowanie formularza...'), { section: 'report' });

    const data = await requestJson('/api/categories');
    const categories = Array.isArray(data.categories) ? data.categories : [];
    const featuredCategory = categories[0]?.name || 'Porządek publiczny';

    renderPage(pageTitle(page), `
      <section class="page-shell page-shell--report">
        <div class="page-head page-head--report">
          <div>
            <p class="page-eyebrow">Nowe zgłoszenie</p>
            <h1>Nowe zgłoszenie</h1>
            <p class="page-subtitle">Opisz, co się wydarzyło i gdzie. Dodawanie załączników oraz tryb anonimowy nie są dostępne w tej wersji.</p>
          </div>
        </div>

        <div class="report-layout">
          <section class="surface-card surface-card--padded">
            <form id="report-form" class="report-form-grid" novalidate>
              <div class="form-block">
                <p class="card-eyebrow">Szczegóły zgłoszenia</p>
                <h2>Opisz, co się wydarzyło</h2>
                <p class="support-copy">Zachowaj krótki tytuł, użyj opisu do podania kontekstu i wpisz możliwie najdokładniejszą lokalizację.</p>
              </div>

              ${fieldGroup('title', 'Tytuł', 'Krótki temat zgłoszenia', 'text', true)}

              <label class="field field--light">
                <span>Kategoria</span>
                <select name="category_id" required>
                  <option value="">Wybierz kategorię</option>
                  ${categories.map((category) => `<option value="${escapeAttr(category.id)}">${escapeHtml(category.name)}</option>`).join('')}
                </select>
                <small class="field-error" data-error-for="category_id"></small>
              </label>

              <label class="field field--light field--wide">
                <span>Opis</span>
                <textarea name="description" rows="6" placeholder="Opisz charakter incydentu, przebieg zdarzenia i ewentualne potrzeby reakcji." required></textarea>
                <small class="field-error" data-error-for="description"></small>
              </label>

              <label class="field field--light field--wide">
                <span>Lokalizacja</span>
                <input name="location" type="text" placeholder="Podaj adres lub współrzędne" required>
                <small class="field-error" data-error-for="location"></small>
              </label>

              <div class="form-actions form-actions--end field--wide">
                <a class="ui-btn ui-btn--secondary" href="/incidents">Anuluj</a>
                <button class="ui-btn ui-btn--primary" type="submit">Wyślij zgłoszenie</button>
              </div>
              <div class="form-message" data-form-message></div>
            </form>
          </section>

          <aside class="stack-column">
            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Przed wysłaniem</p>
                  <h2>Lista kontrolna</h2>
                </div>
              </div>
              <ul class="guideline-list">
                <li>Użyj krótkiego tytułu, który jasno opisuje problem.</li>
                <li>Jeśli to możliwe, podaj dokładne miejsce w polu lokalizacji.</li>
                <li>Opisz, co się wydarzyło, a nie to, co Twoim zdaniem było przyczyną.</li>
                <li>W pilnych sytuacjach awaryjnych najpierw skontaktuj się z odpowiednimi służbami.</li>
              </ul>
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Kategorie</p>
                  <h2>Dostępne typy zgłoszeń</h2>
                </div>
              </div>
              <div class="info-list">
                ${categories.length ? categories.slice(0, 6).map((category) => `
                  <div class="info-list__row">
                    <span>${escapeHtml(category.name)}</span>
                    <strong>${escapeHtml(category.name === featuredCategory ? 'Sugerowana' : 'Dostępna')}</strong>
                  </div>
                `).join('') : '<p class="support-copy">Brak dostępnych kategorii.</p>'}
              </div>
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Po wysłaniu</p>
                  <h2>Co dalej</h2>
                </div>
              </div>
              <p class="support-copy">Zgłoszenie od razu pojawi się na liście. Moderatorzy i administratorzy będą mogli je przejrzeć, zmienić status i dodać notatki do historii.</p>
            </section>
          </aside>
        </div>
      </section>
    `, { section: 'report' });

    const form = root.querySelector('#report-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearFormErrors(form);
      setFormMessage(form, '', '');

      const body = {
        title: form.elements.title.value.trim(),
        location: form.elements.location.value.trim(),
        category_id: form.elements.category_id.value,
        description: form.elements.description.value.trim(),
      };

      setFormBusy(form, true);
      try {
        const result = await requestJson('/api/incidents', { method: 'POST', body });
        window.location.assign(result.redirect || (result.incident?.id ? `/incidents/${result.incident.id}` : '/incidents'));
      } catch (error) {
        applyValidationErrors(form, error.data?.errors || {});
        setFormMessage(form, error.message || 'Nie udało się zapisać zgłoszenia.', 'error');
      } finally {
        setFormBusy(form, false);
      }
    });
  }

  async function renderIncidentDetail() {
    const incidentId = state.incidentId;
    if (!incidentId) {
      renderPage(pageTitle(page), renderEmptyState('Nieprawidłowy identyfikator.', 'Nie udało się odczytać ID zgłoszenia z adresu.'), { section: 'detail' });
      return;
    }

    renderPage(pageTitle(page), renderLoading('Ładowanie zgłoszenia...'), { section: 'detail' });

    const detail = await requestJson(`/api/incidents/${incidentId}`);
    const incident = detail.incident || {};
    const history = Array.isArray(detail.history) ? detail.history : [];
    const meta = detail.meta || {};
    const statuses = Array.isArray(meta.statuses) ? meta.statuses : [];
    const permissions = meta.permissions || {};
    const similar = await loadSimilarIncidents(incident);

    renderPage(pageTitle(page), `
      <section class="page-shell page-shell--detail">
        <div class="detail-layout">
          <section class="surface-card surface-card--padded detail-story">
            <div class="detail-story__header">
              <div class="detail-story__meta">
                <div>
                  <div class="detail-title-row">
                    ${statusPill(incident.status_name || 'new')}
                    ${softPill(incident.category_name || 'Bezpieczeństwo publiczne')}
                  </div>
                  <h1>${escapeHtml(incident.title || 'Bez tytułu')}</h1>
                  <p class="detail-lead">Zgłoszono ${escapeHtml(formatDate(incident.created_at))} przez ${escapeHtml(incident.reporter_name || 'Nieznany użytkownik')}</p>
                </div>
                <div class="detail-date">Zaktualizowano ${escapeHtml(formatDate(incident.updated_at))}</div>
              </div>

              <div class="detail-columns">
                <div class="detail-copy">
                  <p class="card-eyebrow">Opis</p>
                  <h2>Opis zgłoszenia</h2>
                  <p>${escapeHtml(incident.description || 'Brak opisu')}</p>
                </div>

                <div class="detail-facts">
                  <div class="detail-fact"><span>Obszar</span><strong>${escapeHtml(districtLabel(incident.location || 'Nieznany obszar'))}</strong></div>
                  <div class="detail-fact"><span>Zgłaszający</span><strong>${escapeHtml(incident.reporter_name || 'Nieznany użytkownik')}</strong></div>
                  <div class="detail-fact"><span>Lokalizacja</span><strong>${escapeHtml(incident.location || 'Brak lokalizacji')}</strong></div>
                  <div class="detail-fact"><span>Zaktualizowano</span><strong>${escapeHtml(formatDate(incident.updated_at))}</strong></div>
                </div>
              </div>
            </div>

            <section class="detail-section">
              <div class="card-head">
                <div>
                  <p class="card-eyebrow">Historia</p>
                  <h2>Zmiany statusu</h2>
                </div>
              </div>
              ${history.length ? renderTimeline(history, false) : renderEmptyState('Brak historii', 'Nie zapisano jeszcze żadnych zmian.')}
            </section>
          </section>

          <aside class="detail-rail">
            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Podsumowanie</p>
                  <h2>Podsumowanie zgłoszenia</h2>
                </div>
              </div>
              <div class="summary-list">
                <div class="summary-list__row"><span>Status</span><strong>${escapeHtml(statusLabels[incident.status_name] || titleCase(incident.status_name || 'new'))}</strong></div>
                <div class="summary-list__row"><span>Priorytet</span><strong>${escapeHtml(inferPriorityLabel(incident))}</strong></div>
                <div class="summary-list__row"><span>Kategoria</span><strong>${escapeHtml(incident.category_name || 'Brak')}</strong></div>
                <div class="summary-list__row"><span>Obszar</span><strong>${escapeHtml(districtLabel(incident.location || 'Nieznany obszar'))}</strong></div>
              </div>
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Status</p>
                  <h2>Zaktualizuj zgłoszenie</h2>
                </div>
              </div>
              ${permissions.can_change_status ? `
                <form id="incident-admin-form" class="stack-form" novalidate>
                  <label class="field field--light">
                    <span>Status</span>
                    <select name="status_id" required>
                      ${statuses.map((status) => `<option value="${escapeAttr(status.id)}"${String(incident.status_id) === String(status.id) ? ' selected' : ''}>${escapeHtml(statusLabels[status.name] || titleCase(status.name))}</option>`).join('')}
                    </select>
                    <small class="field-error" data-error-for="status_id"></small>
                  </label>
                  <label class="field field--light">
                    <span>Notatka wewnętrzna</span>
                    <textarea name="admin_note" rows="4" placeholder="Dodaj notatkę do historii zgłoszenia"></textarea>
                    <small class="field-error" data-error-for="admin_note"></small>
                  </label>
                  <div class="form-actions form-actions--stack">
                    <button class="ui-btn ui-btn--primary ui-btn--full" type="submit">Zapisz zmianę</button>
                    <a class="ui-btn ui-btn--secondary ui-btn--full" href="/incidents">Wróć do zgłoszeń</a>
                  </div>
                  <div class="form-message" data-form-message></div>
                </form>
              ` : `
                <div class="empty-note">
                  <strong>Ta sprawa nie jest edytowalna.</strong>
                  <p>Możesz śledzić historię i wrócić do listy zgłoszeń.</p>
                </div>
              `}
            </section>

            <section class="surface-card surface-card--padded">
              <div class="card-head card-head--compact">
                <div>
                  <p class="card-eyebrow">Powiązane zgłoszenia</p>
                  <h2>Podobne sprawy</h2>
                </div>
              </div>
              ${similar.length ? `
                <div class="similar-grid">
                  ${similar.map((item) => similarCard(item)).join('')}
                </div>
              ` : renderEmptyState('Brak podobnych zgłoszeń', 'Nie udało się znaleźć zbliżonych przypadków.')}
            </section>
          </aside>
        </div>
      </section>
    `, { section: 'detail' });

    const form = root.querySelector('#incident-admin-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearFormErrors(form);
      setFormMessage(form, '', '');
      setFormBusy(form, true);

      try {
        await requestJson(`/api/incidents/${incidentId}`, {
          method: 'PATCH',
          body: {
            status_id: form.elements.status_id.value,
            admin_note: form.elements.admin_note.value.trim(),
          },
        });
        await renderIncidentDetail();
      } catch (error) {
        applyValidationErrors(form, error.data?.errors || {});
        setFormMessage(form, error.message || 'Nie udało się zaktualizować zgłoszenia.', 'error');
      } finally {
        setFormBusy(form, false);
      }
    });
  }

  async function renderAdminCenter() {
    renderPage(pageTitle(page), renderLoading('Ładowanie administracji...'), { section: 'admin' });

    const data = await requestJson('/api/admin/users');
    const users = Array.isArray(data.users) ? data.users : [];
    const roles = Array.isArray(data.roles) ? data.roles : [];
    const countsByRole = Array.isArray(data.counts_by_role) ? data.counts_by_role : [];

    renderPage(pageTitle(page), `
      <section class="page-shell page-shell--admin">
        <div class="page-head page-head--dashboard">
          <div>
            <p class="page-eyebrow">Administracja</p>
            <h1>Administracja</h1>
            <p class="page-subtitle">Zarządzaj rolami kont i kontroluj zasady dostępu.</p>
          </div>
          <div class="page-actions">
            <a class="ui-btn ui-btn--secondary" href="/dashboard">Panel</a>
            <a class="ui-btn ui-btn--primary" href="/incidents">Zgłoszenia</a>
          </div>
        </div>

        <section class="metrics-grid metrics-grid--compact">
          ${countsByRole.map((row) => metricCard(roleLabels[row.role_name] || titleCase(row.role_name || ''), row.users_count ?? 0, 'aktywnych kont', 'metric-card--orange')).join('')}
        </section>

        <div class="dashboard-layout">
          <section class="surface-card surface-card--padded">
            <div class="card-head">
              <div>
                <p class="card-eyebrow">Zasady</p>
                <h2>Zmiany ról</h2>
              </div>
            </div>
            <div class="admin-notes">
              <div class="admin-note">
                <strong>W systemie musi pozostać co najmniej jeden administrator.</strong>
                <p>Usunięcie ostatniego administratora zablokowałoby kolejne zmiany dostępu.</p>
              </div>
              <div class="admin-note">
                <strong>Zmiany są stosowane od razu.</strong>
                <p>Aktualizacja roli zapisuje się bez przeładowania strony i będzie widoczna po odświeżeniu.</p>
              </div>
              <div class="admin-note">
                <strong>${escapeHtml(users.length)} ${users.length === 1 ? 'konto znajduje się' : 'konta znajdują się'} w systemie.</strong>
                <p>${escapeHtml(countsByRole.length)} ${countsByRole.length === 1 ? 'typ roli jest obecnie przypisany' : 'typy ról są obecnie przypisane'}.</p>
              </div>
            </div>
          </section>

          <section class="surface-card surface-card--padded">
            <div class="card-head">
              <div>
                <p class="card-eyebrow">Użytkownicy</p>
                <h2>Zmień role</h2>
              </div>
              <span class="card-meta">Bez przeładowania</span>
            </div>
            ${users.length ? `
              <div class="user-stack">
                ${users.map((row) => userCard(row, roles)).join('')}
              </div>
            ` : renderEmptyState('Brak użytkowników', 'Nie udało się pobrać listy kont.')}
          </section>
        </div>
      </section>
    `, { section: 'admin' });

    root.querySelectorAll('[data-role-form]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const userId = form.getAttribute('data-role-form');
        const select = form.querySelector('select[name="role"]');
        const message = root.querySelector(`[data-role-message="${cssEscape(userId)}"]`);
        setNodeMessage(message, '', '');
        setFormBusy(form, true);

        try {
          await requestJson(`/api/admin/users/${userId}`, { method: 'PATCH', body: { role: select.value } });
          setNodeMessage(message, 'Rola zaktualizowana.', 'success');
          await renderAdminCenter();
        } catch (error) {
          setNodeMessage(message, error.message || 'Nie udało się zaktualizować roli.', 'error');
        } finally {
          setFormBusy(form, false);
        }
      });
    });
  }

  function renderPage(title, content, options = {}) {
    document.title = title;
    const section = options.section || sectionForPage(page);
    const navKey = navKeyForSection(section);
    const nav = renderMobileNav(navKey);

    root.innerHTML = `
      <div class="app-shell app-shell--${escapeAttr(section)}">
        <aside class="app-sidebar">
          <div class="sidebar-brand">
            <div class="sidebar-brand__name">SAFECITY</div>
            <div class="sidebar-brand__sub">Miejski system zgłoszeń</div>
          </div>

          <nav class="sidebar-nav" aria-label="Nawigacja główna">
            ${renderSidebarNav(navKey)}
          </nav>

          <div class="sidebar-footer">
            <a class="sidebar-alert" href="/incidents/report">Pilne zgłoszenie</a>
            <form class="sidebar-logout" action="/logout" method="post">
              <input type="hidden" name="_csrf" value="${escapeAttr(csrfToken)}">
              <button type="submit" class="sidebar-link sidebar-link--button">Wyloguj</button>
            </form>
          </div>
        </aside>

        <div class="app-stage">
          <header class="app-topbar">
            <div class="topbar-context" aria-label="Current section">
              <span class="topbar-context__page">${escapeHtml(topbarContextLabel(section))}</span>
              <span class="topbar-context__meta">${escapeHtml(roleLabels[user.role] || titleCase(user.role || ''))}</span>
            </div>

            <div class="topbar-userbar">
              <div class="topbar-usertext">
                <strong>${escapeHtml(user.name || 'Użytkownik')}</strong>
                <span>${escapeHtml(user.email || '')}</span>
              </div>
              <div class="topbar-user">
                <span>${escapeHtml(initialsForUser(user.name || user.email || 'SC'))}</span>
              </div>
            </div>
          </header>

          <main class="app-main app-main--${escapeAttr(section)}">
            ${content}
          </main>
        </div>
        ${nav}
      </div>
    `;
  }

  function renderLoading(label) {
    return `
      <section class="surface-card surface-card--padded">
        <div class="empty-state">
          <div class="loading-spinner" aria-hidden="true"></div>
          <p>${escapeHtml(label)}</p>
        </div>
      </section>
    `;
  }

  function renderErrorPanel(error) {
    return `
      <section class="surface-card surface-card--padded">
        <div class="empty-state empty-state--error">
          <h2>Wystąpił problem</h2>
          <p>${escapeHtml(typeof error === 'string' ? error : error?.message || 'Nie udało się wczytać danych.')}</p>
          <a class="ui-btn ui-btn--primary" href="/dashboard">Wróć do panelu</a>
        </div>
      </section>
    `;
  }

  function renderEmptyState(title, text) {
    return `
      <div class="empty-state">
        <h3>${escapeHtml(title)}</h3>
        <p>${escapeHtml(text)}</p>
      </div>
    `;
  }

  function metricCard(label, value, caption, modifier = '') {
    return `
      <article class="metric-card ${modifier}">
        <span>${escapeHtml(label)}</span>
        <strong>${escapeHtml(value ?? 0)}</strong>
        <small>${escapeHtml(caption)}</small>
      </article>
    `;
  }

  function incidentCard(incident) {
    const category = incident.category_name || 'Inne';
    const priority = inferPriorityLabel(incident);
    const reporter = incident.reporter_name || 'Nieznany użytkownik';

    return `
      <article class="report-card">
        <a class="report-card__link" href="/incidents/${escapeHtml(incident.id)}">
          <div class="report-card__body">
            <div class="report-card__meta">
              <div>
                <span class="report-card__category">${escapeHtml(category)}</span>
                <span class="report-card__time">${escapeHtml(relativeAge(incident.created_at))}</span>
              </div>
              ${statusPill(incident.status_name || 'new')}
            </div>
            <h3>${escapeHtml(incident.title || 'Bez tytułu')}</h3>
            <p>${escapeHtml(truncateText(incident.description || '', 132))}</p>
            <div class="report-card__facts">
              <div class="report-card__fact">
                <span>Obszar</span>
                <strong>${escapeHtml(districtLabel(incident.location || 'Nieznana dzielnica'))}</strong>
              </div>
              <div class="report-card__fact">
                <span>Priorytet</span>
                <strong>${escapeHtml(priority)}</strong>
              </div>
              <div class="report-card__fact">
                <span>Zgłaszający</span>
                <strong>${escapeHtml(reporter)}</strong>
              </div>
            </div>
          </div>
        </a>
      </article>
    `;
  }

  function renderIncidentTable(incidents) {
    return `
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Kategoria</th>
              <th>Lokalizacja</th>
              <th>Data zgłoszenia</th>
              <th>Priorytet</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${incidents.slice(0, 6).map((incident) => `
              <tr>
                <td><a class="table-link" href="/incidents/${escapeHtml(incident.id)}">#INC-${escapeHtml(incident.id)}</a></td>
                <td>${escapeHtml(incident.category_name || 'Bezpieczeństwo publiczne')}</td>
                <td>${escapeHtml(districtLabel(incident.location || 'Nieznana lokalizacja'))}</td>
                <td>${escapeHtml(shortDate(incident.created_at))}</td>
                <td>${escapeHtml(inferPriorityLabel(incident))}</td>
                <td>${statusPill(incident.status_name || 'new')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function renderCategoryDistribution(rows) {
    const max = Math.max(...rows.map((row) => Number(row.incidents_count || 0)), 1);

    return `
      <div class="distribution-list">
        ${rows.map((row) => {
          const value = Number(row.incidents_count || 0);
          const width = Math.max(12, Math.round((value / max) * 100));
          return `
            <div class="distribution-row">
              <div class="distribution-row__label">
                <span>${escapeHtml(row.category_name || 'Brak kategorii')}</span>
                <strong>${escapeHtml(value)}</strong>
              </div>
              <div class="distribution-row__track">
                <span style="width:${width}%"></span>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  function renderTrendChart(stats, incidents) {
    const seed = [
      Number(stats.new_count || 0) + 1,
      Number(stats.in_progress_count || 0) + 2,
      Number(stats.resolved_count || 0) + 1,
      Number(stats.total || 0) > 0 ? Math.max(1, Math.round(Number(stats.total) / 3)) : 2,
      incidents.length || 2,
      Number(stats.new_count || 0) + Number(stats.resolved_count || 0) + 1,
      Number(stats.in_progress_count || 0) + 1,
      Number(stats.total || 0) > 0 ? Math.max(1, Math.round(Number(stats.total) / 2.5)) : 3,
    ];
    const max = Math.max(...seed, 1);

    return `
      <div class="trend-chart">
        ${seed.map((value, index) => `
          <div class="trend-chart__bar ${index % 3 === 1 ? 'is-accent' : ''}">
            <span style="height:${Math.max(12, Math.round((value / max) * 100))}%"></span>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderMetricList(rows, labelKey, valueKey, roleLabel = false) {
    return `
      <div class="metric-list">
        ${rows.map((row) => `
          <div class="metric-row">
            <span>${escapeHtml(roleLabel ? (roleLabels[row[labelKey]] || titleCase(row[labelKey] || '')) : row[labelKey] || 'Brak')}</span>
            <strong>${escapeHtml(row[valueKey] ?? 0)}</strong>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderTimeline(rows, compact) {
    return `
      <ol class="timeline">
        ${rows.map((row) => `
          <li class="timeline__item${compact ? ' timeline__item--compact' : ''}">
            <div class="timeline__head">
              <strong>${escapeHtml(statusTransition(row.from_status_name, row.to_status_name))}</strong>
              <span>${escapeHtml(formatDate(row.created_at))}</span>
            </div>
            <p>${escapeHtml(row.actor_name || 'System')}</p>
            ${row.note ? `<div class="timeline__note">${escapeHtml(row.note)}</div>` : ''}
          </li>
        `).join('')}
      </ol>
    `;
  }

  function similarCard(item) {
    return `
      <a class="similar-card" href="/incidents/${escapeHtml(item.id)}">
        <div class="similar-card__body">
          <strong>${escapeHtml(item.title || 'Bez tytułu')}</strong>
          <span>${escapeHtml(districtLabel(item.location || 'Nieznany obszar'))}</span>
          <span>${escapeHtml(relativeAge(item.created_at))}</span>
        </div>
      </a>
    `;
  }

  function userCard(row, roles) {
    return `
      <article class="user-card">
        <div class="user-card__main">
          <div class="user-card__visual">${escapeHtml((row.full_name || 'U')[0] || 'U')}</div>
          <div>
            <strong>${escapeHtml(row.full_name || 'Bez nazwy')}</strong>
            <span>${escapeHtml(row.email || '')}</span>
          </div>
        </div>
        <div class="user-card__stats">
          <span>${escapeHtml(roleLabels[row.role_name] || titleCase(row.role_name || ''))}</span>
          <span>${escapeHtml(row.incidents_count ?? 0)} zgłoszeń</span>
          <span>${escapeHtml(row.active_incidents_count ?? 0)} aktywnych</span>
        </div>
        <form class="user-card__form" data-role-form="${escapeHtml(row.id)}">
          <label class="field field--light field--compact">
            <span>Rola</span>
            <select name="role">
              ${roles.map((role) => `<option value="${escapeAttr(role.name)}"${role.name === row.role_name ? ' selected' : ''}>${escapeHtml(roleLabels[role.name] || titleCase(role.name))}</option>`).join('')}
            </select>
          </label>
          <button class="ui-btn ui-btn--secondary" type="submit">Zapisz</button>
        </form>
      <div class="form-message" data-role-message="${escapeHtml(row.id)}"></div>
    </article>
  `;
  }

  function renderSidebarNav(active) {
    const items = [
      ['dashboard', '/dashboard', 'Panel', 'dashboard'],
      ['incidents', '/incidents', 'Zgłoszenia', 'reports'],
      ['report', '/incidents/report', 'Nowe zgłoszenie', 'report'],
    ];
    if (user.role === 'admin') {
      items.push(['admin', '/admin', 'Administracja', 'admin']);
    }

    return items.map(([key, href, label, icon]) => `
      <a class="sidebar-nav__item${active === key ? ' is-active' : ''}" href="${href}"${active === key ? ' aria-current="page"' : ''}>
        <span class="sidebar-nav__icon" aria-hidden="true">${iconMarkup(icon)}</span>
        <span class="sidebar-nav__label">${label}</span>
      </a>
    `).join('');
  }

  function renderMobileNav(active) {
    const items = [
      ['dashboard', '/dashboard', 'Panel', 'dashboard'],
      ['incidents', '/incidents', 'Zgłoszenia', 'reports'],
      ['report', '/incidents/report', 'Dodaj', 'report'],
    ];
    if (user.role === 'admin') items.push(['admin', '/admin', 'Administracja', 'admin']);

    return `
      <nav class="mobile-nav" aria-label="Nawigacja mobilna">
        ${items.map(([key, href, label, icon]) => `
          <a href="${href}"${active === key ? ' aria-current="page"' : ''}>
            <span class="mobile-nav__icon" aria-hidden="true">${iconMarkup(icon)}</span>
            <span>${label}</span>
          </a>
        `).join('')}
      </nav>
    `;
  }

  function topbarContextLabel(section) {
    switch (section) {
      case 'dashboard': return 'Panel';
      case 'incidents': return 'Zgłoszenia';
      case 'report': return 'Nowe zgłoszenie';
      case 'detail': return 'Szczegóły zgłoszenia';
      case 'admin': return 'Administracja';
      default: return 'SafeCity';
    }
  }

  function iconMarkup(name) {
    switch (name) {
      case 'dashboard':
        return `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"></rect>
            <rect x="13.5" y="3.5" width="7" height="5" rx="1.5"></rect>
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"></rect>
            <rect x="13.5" y="11.5" width="7" height="9" rx="1.5"></rect>
          </svg>
        `;
      case 'reports':
        return `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M8 3.5h6l4.5 4.5v10a2.5 2.5 0 0 1-2.5 2.5h-8A2.5 2.5 0 0 1 5.5 18V6A2.5 2.5 0 0 1 8 3.5Z"></path>
            <path d="M13.5 3.5V9h5"></path>
            <path d="M8.5 12h7"></path>
            <path d="M8.5 15.5h7"></path>
          </svg>
        `;
      case 'report':
        return `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5.5v13"></path>
            <path d="M5.5 12h13"></path>
            <rect x="3.5" y="3.5" width="17" height="17" rx="4"></rect>
          </svg>
        `;
      case 'admin':
        return `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3.5 6.5 5.8v5.7c0 4.3 2.2 7.2 5.5 9 3.3-1.8 5.5-4.7 5.5-9V5.8L12 3.5Z"></path>
            <path d="M12 9v6"></path>
            <path d="M9 12h6"></path>
          </svg>
        `;
      default:
        return '';
    }
  }

  async function loadSimilarIncidents(incident) {
    const data = await requestJson('/api/incidents');
    const incidents = Array.isArray(data.incidents) ? data.incidents : [];

    return incidents
      .filter((item) => item.id !== incident.id && (!incident.category_name || item.category_name === incident.category_name))
      .slice(0, 2);
  }

  function fieldGroup(name, label, placeholder, type, light = false) {
    return `
      <label class="field ${light ? 'field--light' : ''}">
        <span>${escapeHtml(label)}</span>
        <input name="${escapeAttr(name)}" type="${escapeAttr(type)}" placeholder="${escapeAttr(placeholder)}" required>
        <small class="field-error" data-error-for="${escapeAttr(name)}"></small>
      </label>
    `;
  }

  function pageTitle(currentPage) {
    switch (currentPage) {
      case 'dashboard': return 'Panel — SafeCity';
      case 'incidents': return 'Incydenty — SafeCity';
      case 'report': return 'Nowe zgłoszenie — SafeCity';
      case 'incident-detail': return 'Szczegóły zgłoszenia — SafeCity';
      case 'admin': return 'Administracja — SafeCity';
      default: return 'SafeCity';
    }
  }

  function sectionForPage(currentPage) {
    return currentPage === 'incident-detail' ? 'detail' : currentPage;
  }

  function navKeyForSection(section) {
    if (section === 'detail') return 'incidents';
    return section;
  }

  function statusTransition(fromStatus, toStatus) {
    const fromLabel = fromStatus ? (statusLabels[fromStatus] || titleCase(fromStatus)) : 'Brak';
    const toLabel = statusLabels[toStatus] || titleCase(toStatus || '');
    return `${fromLabel} -> ${toLabel}`;
  }

  function statusPill(statusName) {
    const normalized = String(statusName || 'new');
    return `<span class="status-pill status-pill--${escapeAttr(normalized)}">${escapeHtml(statusLabels[normalized] || titleCase(normalized))}</span>`;
  }

  function softPill(text) {
    return `<span class="status-pill status-pill--soft">${escapeHtml(text)}</span>`;
  }

  function buildIncidentQuery(filters) {
    const params = new URLSearchParams();
    if (filters.status) params.set('status', filters.status);
    if (filters.category_id) params.set('category_id', filters.category_id);
    if (filters.search) params.set('search', filters.search);
    return params.toString();
  }

  function readBootstrap() {
    const script = document.getElementById('app-bootstrap');
    if (!script) return {};
    try {
      return JSON.parse(script.textContent || '{}') || {};
    } catch {
      return {};
    }
  }

  function readFilters() {
    const params = new URLSearchParams(window.location.search);
    return {
      status: params.get('status') || '',
      category_id: params.get('category_id') || '',
      search: params.get('search') || '',
    };
  }

  function pageFromPath(pathname) {
    if (pathname === '/dashboard') return 'dashboard';
    if (pathname === '/incidents') return 'incidents';
    if (pathname === '/incidents/report') return 'report';
    if (/^\/incidents\/\d+/.test(pathname)) return 'incident-detail';
    if (pathname === '/admin' || pathname === '/admin/dashboard') return 'admin';
    return 'dashboard';
  }

  function extractIncidentId(pathname) {
    const match = pathname.match(/^\/incidents\/(\d+)/);
    return match ? Number(match[1]) : null;
  }

  async function requestJson(url, options = {}) {
    const init = {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      ...options,
    };

    if (init.body && typeof init.body === 'object' && !(init.body instanceof FormData)) {
      init.headers = {
        ...init.headers,
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      };
      init.body = JSON.stringify(init.body);
    }

    const response = await fetch(url, init);
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json().catch(() => null) : null;

    if (response.status === 401 && payload?.redirect) {
      window.location.assign(payload.redirect);
      throw new Error(payload.error || 'Wymagane jest zalogowanie.');
    }

    if (!response.ok) {
      const error = new Error(payload?.error || firstError(payload?.errors) || `HTTP ${response.status}`);
      error.status = response.status;
      error.data = payload || {};
      throw error;
    }

    return payload || {};
  }

  function firstError(errors) {
    if (!errors || typeof errors !== 'object') return '';
    return Object.values(errors).find((value) => typeof value === 'string') || '';
  }

  function clearFormErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((node) => {
      node.textContent = '';
    });
    form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
  }

  function applyValidationErrors(form, errors) {
    if (!errors || typeof errors !== 'object') return;
    Object.entries(errors).forEach(([field, message]) => {
      const input = form.elements[field];
      const errorNode = form.querySelector(`[data-error-for="${cssEscape(field)}"]`);
      if (input) input.classList.add('is-invalid');
      if (errorNode && typeof message === 'string') errorNode.textContent = message;
    });
  }

  function setFormMessage(form, message, kind) {
    const node = form.querySelector('[data-form-message]');
    setNodeMessage(node, message, kind);
  }

  function setNodeMessage(node, message, kind) {
    if (!node) return;
    node.textContent = message;
    node.className = 'form-message';
    if (kind) node.classList.add(`form-message--${kind}`);
  }

  function setFormBusy(form, busy) {
    form.querySelectorAll('button, input, select, textarea').forEach((node) => {
      if (node.type !== 'hidden') node.disabled = busy;
    });
  }

  function truncateText(text, length) {
    const value = String(text || '').replace(/\s+/g, ' ').trim();
    if (value.length <= length) return value;
    return `${value.slice(0, length - 1).trim()}...`;
  }

  function formatDate(value) {
    if (!value) return 'Brak daty';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  }

  function shortDate(value) {
    if (!value) return 'N/A';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(date);
  }

  function titleCase(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (match) => match.toUpperCase());
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(String(value));
    }
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function inferPriorityLabel(incident) {
    const status = String(incident.status_name || '').toLowerCase();
    if (status === 'new') return 'Wysoki';
    if (status === 'in_progress') return 'Średni';
    if (status === 'resolved') return 'Niski';
    return 'Średni';
  }

  function relativeAge(value) {
    if (!value) return 'przed chwilą';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'niedawno';
    const deltaMs = Math.max(0, Date.now() - date.getTime());
    const deltaMinutes = Math.round(deltaMs / 60000);
    if (deltaMinutes < 1) return 'przed chwilą';
    if (deltaMinutes < 60) return `${deltaMinutes} min temu`;
    const deltaHours = Math.round(deltaMinutes / 60);
    if (deltaHours < 24) return `${deltaHours} godz. temu`;
    return `${Math.round(deltaHours / 24)} dni temu`;
  }

  function districtLabel(location) {
    const value = String(location || '').split(',')[0].trim();
    return value || 'Nieznana dzielnica';
  }

  function initialsForUser(value) {
    const parts = String(value || 'SC').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'SC';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase();
  }

})();
