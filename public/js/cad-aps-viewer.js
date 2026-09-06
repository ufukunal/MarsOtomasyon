(() => {
    const host = document.querySelector('[data-cad-aps-viewer]');
    if (!(host instanceof HTMLElement)) return;

    const urn = host.dataset.urn;
    const tokenUrl = host.dataset.tokenUrl;
    const target = document.getElementById('mars-aps-viewer');
    if (!urn || !tokenUrl || !(target instanceof HTMLElement) || typeof Autodesk === 'undefined') return;

    const getAccessToken = async (onTokenReady) => {
        const response = await fetch(tokenUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) throw new Error(`token ${response.status}`);
        const token = await response.json();
        onTokenReady(token.access_token, token.expires_in);
    };

    Autodesk.Viewing.Initializer({
        env: 'AutodeskProduction2',
        api: 'streamingV2',
        getAccessToken,
    }, () => {
        const viewer = new Autodesk.Viewing.GuiViewer3D(target, {
            disabledExtensions: ['Autodesk.Viewing.MarkupsCore'],
        });
        viewer.start();
        Autodesk.Viewing.Document.load(
            `urn:${urn}`,
            (doc) => {
                const geometry = doc.getRoot().getDefaultGeometry();
                viewer.loadDocumentNode(doc, geometry).then(() => {
                    host.dataset.cadRendered = 'true';
                });
            },
            () => {
                host.dataset.cadRendered = 'failed';
            },
        );
    });
})();
