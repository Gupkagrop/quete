const puppeteer = require('C:\\Users\\denis\\AppData\\Roaming\\npm\\node_modules\\@modelcontextprotocol\\server-puppeteer\\node_modules\\puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    try {
        console.log('Navigating to http://pre-alpha/register.php...');
        await page.goto('http://pre-alpha/register.php', { waitUntil: 'networkidle2' });
        console.log('Current URL:', page.url());
        
        const html = await page.evaluate(() => document.body.innerHTML);
        console.log('Body length:', html.length);
        
        const btn = await page.$('.auth-btn');
        console.log('Found .auth-btn:', !!btn);
        if (btn) {
            const text = await page.evaluate(el => el.textContent, btn);
            console.log('.auth-btn text:', text);
        } else {
            console.log('HTML Content snippet:\n', html.substring(0, 1000));
        }
    } catch (e) {
        console.error('Error:', e);
    } finally {
        await browser.close();
    }
})();
