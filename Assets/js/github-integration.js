(function () {
    'use strict';

    const appUrl = (window.leantime && window.leantime.appUrl || '').replace(/\/$/, '');
    const csrf = (node) => node.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

    async function loadTodo(node) {
        node.dataset.githubInitialized = '1';
        const id = node.dataset.ticketId;
        node.innerHTML = '<p>Loading GitHub information…</p>';
        try {
            const response = await fetch(`${appUrl}/GitHubIntegration/todos/${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'GitHub information could not be loaded.');
            if (!data.connected) { node.innerHTML = data.appConfigured ? '<p>This project has no GitHub repository configured. Configure it in Project Settings → Integrations.</p>' : '<p>A Leantime administrator needs to configure the GitHub App first.</p>'; return; }
            if (!data.githubLinked) {
                if (!data.appConfigured) { node.innerHTML = '<p>A Leantime administrator needs to configure the GitHub App before accounts can connect.</p>'; return; }
                const returnTo = encodeURIComponent(`/projects/showProject/${node.dataset.projectId}#integrations`);
                node.innerHTML = `<p>Connect your GitHub account to see <strong>${esc(data.repository || 'this repository')}</strong>.</p><a class="btn btn-default" href="${appUrl}/GitHubIntegration/connect?return_to=${returnTo}">Connect GitHub</a>`;
                return;
            }

            let html = `<p>Repository: <strong>${esc(data.repository)}</strong></p>`;
            html += '<h5>Branches for this to-do</h5>';
            html += data.branches.length ? '<ul>' + data.branches.map((branch) => `<li><a href="${esc(branch.url)}" target="_blank" rel="noopener">${esc(branch.name)}</a></li>`).join('') + '</ul>' : '<p>No matching branches.</p>';
            html += '<h5>Pull requests</h5>';
            html += data.pullRequests.length ? '<ul>' + data.pullRequests.map((pr) => `<li><a href="${esc(pr.url)}" target="_blank" rel="noopener">#${esc(pr.number)} ${esc(pr.title)}</a> <small>${esc(pr.state)} · ${esc(pr.branch)}</small></li>`).join('') + '</ul>' : '<p>No matching pull requests.</p>';
            if (data.canCreateBranch) html += `<form data-github-branch-form><label for="github-branch-${esc(id)}">Create a branch</label><div class="github-branch-row"><input class="form-control" id="github-branch-${esc(id)}" name="summary" maxlength="100" required placeholder="Short branch description"><button class="btn btn-primary" type="submit">Create branch</button></div><span data-github-branch-status role="status"></span></form>`;
            else html += '<p>Your Leantime role does not allow branch creation for this to-do.</p>';
            node.innerHTML = html;
        } catch (error) {
            node.innerHTML = `<div class="alert alert-warning" role="alert">${esc(error.message)}</div>`;
        }
    }

    function scan(root) {
        if (root.matches && root.matches('[data-github-todo="1"]') && !root.dataset.githubInitialized) loadTodo(root);
        if (root.querySelectorAll) root.querySelectorAll('[data-github-todo="1"]:not([data-github-initialized])').forEach((node) => loadTodo(node));
    }
    scan(document);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => { if (node.nodeType === 1) scan(node); })))
        .observe(document.body, { childList: true, subtree: true });

    document.addEventListener('submit', async (event) => {
        const projectForm = event.target.closest('[data-github-project-form]');
        const branchForm = event.target.closest('[data-github-branch-form]');
        if (!projectForm && !branchForm) return;
        event.preventDefault();
        const form = projectForm || branchForm;
        const status = form.querySelector('[data-github-save-status], [data-github-branch-status]');
        const button = form.querySelector('button[type="submit"]');
        if (status) status.textContent = ' Saving…';
        if (button) button.disabled = true;
        try {
            const action = projectForm ? form.action : `${appUrl}/GitHubIntegration/todos/${encodeURIComponent(form.closest('[data-github-todo]').dataset.ticketId)}/branches`;
            const response = await fetch(action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]')?.value || form.closest('[data-github-todo]')?.dataset.csrf || '' },
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The request could not be completed.');
            if (status) status.textContent = projectForm ? ' Saved.' : ` Created ${result.name}.`;
            if (branchForm) loadTodo(form.closest('[data-github-todo]'));
        } catch (error) {
            if (status) status.textContent = ` ${error.message}`;
        } finally {
            if (button && button.isConnected) button.disabled = false;
        }
    });

    document.addEventListener('click', async (event) => {
        const disconnectUser = event.target.closest('[data-github-disconnect]');
        const disconnectProject = event.target.closest('[data-github-project-disconnect]');
        if (!disconnectUser && !disconnectProject) return;
        const button = disconnectUser || disconnectProject;
        const panel = button.closest('.github-project-panel');
        const projectId = button.dataset.githubProjectDisconnect;
        const status = panel.querySelector('[data-github-project-status]') || document.createElement('span');
        if (!window.confirm(disconnectUser ? 'Disconnect your GitHub account from Leantime?' : 'Disconnect this repository from the project? This will not delete GitHub branches.')) return;
        button.disabled = true;
        try {
            const endpoint = disconnectUser ? `${appUrl}/GitHubIntegration/disconnect` : `${appUrl}/GitHubIntegration/projects/${encodeURIComponent(projectId)}`;
            const response = await fetch(endpoint, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': panel.dataset.csrf } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Could not disconnect.');
            if (disconnectProject) status.textContent = ' Repository disconnected. Reload the page to configure another.';
            else location.reload();
        } catch (error) { status.textContent = ` ${error.message}`; button.disabled = false; }
    });
})();
