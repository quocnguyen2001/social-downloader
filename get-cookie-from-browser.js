(() => {
    const host = location.hostname;
    if (!/(^|\.)youtube\.com$/.test(host)) {
        console.warn("Hãy chạy đoạn script này trên tab *.youtube.com (vd: https://www.youtube.com/).");
        return;
    }

    const cookiesStr = document.cookie || "";
    const cookies = cookiesStr
        .split(/;\s*/)
        .filter(Boolean)
        .map(pair => {
            const eq = pair.indexOf("=");
            const name = eq >= 0 ? pair.slice(0, eq) : pair;
            const value = eq >= 0 ? pair.slice(eq + 1) : "";
            return { name, value };
        });

    if (cookies.length === 0) {
        console.info("Không đọc được cookie (có thể tất cả đều là HttpOnly hoặc không có cookie cho path hiện tại).");
    } else {
        console.table(cookies);
    }

    const json = JSON.stringify({ domain: host, cookies }, null, 2);

    // Tự động tải file .txt
    const blob = new Blob([json], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `youtube_cookies_${Date.now()}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    console.log("Đã tải file TXT chứa cookie.");
})();
