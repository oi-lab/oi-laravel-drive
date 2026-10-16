<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Déposer un fichier</title>
    <style>
        :root { color-scheme: light dark; --bg: #f8fafc; --card: #fff; --text: #0f172a; --muted: #64748b; --border: #cbd5e1; --accent: #2563eb; --ok: #15803d; --error: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f172a; --card: #1e293b; --text: #f1f5f9; --muted: #94a3b8; --border: #475569; --accent: #60a5fa; --ok: #4ade80; --error: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, sans-serif; }
        main { width: 100%; max-width: 28rem; background: var(--card); border-radius: 12px; padding: 32px; box-shadow: 0 10px 30px rgb(0 0 0 / .08); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; text-align: center; }
        p { margin: 0; text-align: center; color: var(--muted); }
        label { display: block; margin-top: 24px; border: 2px dashed var(--border); border-radius: 8px; padding: 32px; text-align: center; cursor: pointer; }
        label:hover, label.over { border-color: var(--accent); }
        small { display: block; margin-top: 8px; color: var(--muted); }
        input { display: none; }
        #message { margin-top: 16px; font-size: .875rem; }
        .ok { color: var(--ok); } .error { color: var(--error); }
    </style>
</head>
<body>
    <main>
        <h1>Déposer un fichier</h1>
        <p>« {{ $upload->filename }} » dans <strong>{{ $upload->drive->name }}</strong>, à la demande de votre assistant IA.</p>

        @if ($uploadUrl)
            <label id="dropzone" for="file">
                Glissez le fichier ici, ou cliquez pour le choisir
                <small>{{ $maxMegabytes }} Mo au plus</small>
                <input id="file" type="file">
            </label>
            <p id="message" role="status" aria-live="polite"></p>
        @else
            <p class="error" style="margin-top: 24px">Ce lien a déjà servi ou a expiré. Demandez-en un nouveau à votre assistant.</p>
        @endif
    </main>

    @if ($uploadUrl)
        <script>
            const input = document.getElementById('file');
            const dropzone = document.getElementById('dropzone');
            const message = document.getElementById('message');

            const send = async (file) => {
                message.className = '';
                message.textContent = 'Envoi en cours…';
                const response = await fetch(@json($uploadUrl), { method: 'PUT', body: file, headers: { 'Content-Type': file.type || 'application/octet-stream', 'Accept': 'application/json' } });
                const body = await response.json().catch(() => ({}));
                if (response.ok) {
                    dropzone.remove();
                    message.className = 'ok';
                    message.textContent = 'Fichier reçu. Vous pouvez fermer cette fenêtre et revenir à votre assistant.';
                } else {
                    message.className = 'error';
                    message.textContent = body.message || 'Le dépôt a échoué.';
                }
            };

            input.addEventListener('change', () => input.files[0] && send(input.files[0]));
            dropzone.addEventListener('dragover', (event) => { event.preventDefault(); dropzone.classList.add('over'); });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('over'));
            dropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                dropzone.classList.remove('over');
                if (event.dataTransfer.files[0]) {
                    send(event.dataTransfer.files[0]);
                }
            });
        </script>
    @endif
</body>
</html>
