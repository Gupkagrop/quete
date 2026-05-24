const puppeteer = require('C:\\Users\\denis\\AppData\\Roaming\\npm\\node_modules\\@modelcontextprotocol\\server-puppeteer\\node_modules\\puppeteer');
const fs = require('fs');
const path = require('path');

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

(async () => {
    console.log('=== STARTING MULTIPLAYER TEST ===');
    const screenshotDir = 'C:\\Users\\denis\\.gemini\\antigravity\\browser_recordings';
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    let page1 = null;
    let page2 = null;

    try {
        // Create two separate browser contexts for isolation
        const context1 = await browser.createBrowserContext();
        const context2 = await browser.createBrowserContext();

        page1 = await context1.newPage();
        page2 = await context2.newPage();

        // Log browser console messages and handle dialogs
        page1.on('console', msg => console.log('PAGE 1 LOG:', msg.text()));
        page2.on('console', msg => console.log('PAGE 2 LOG:', msg.text()));

        // Intercept and log all AJAX responses for debugging
        page1.on('response', async response => {
            const url = response.url();
            if (url.includes('ajax/')) {
                try {
                    const text = await response.text();
                    console.log(`PAGE 1 AJAX RESPONSE (${url.split('/').pop()}): status=${response.status()} body=${text}`);
                } catch (e) {
                    console.log(`PAGE 1 AJAX RESPONSE (${url.split('/').pop()}): status=${response.status()} (failed to read body)`);
                }
            }
        });
        page2.on('response', async response => {
            const url = response.url();
            if (url.includes('ajax/')) {
                try {
                    const text = await response.text();
                    console.log(`PAGE 2 AJAX RESPONSE (${url.split('/').pop()}): status=${response.status()} body=${text}`);
                } catch (e) {
                    console.log(`PAGE 2 AJAX RESPONSE (${url.split('/').pop()}): status=${response.status()} (failed to read body)`);
                }
            }
        });

        page1.on('dialog', async dialog => {
            console.log('PAGE 1 DIALOG:', dialog.message());
            await dialog.dismiss();
        });
        page2.on('dialog', async dialog => {
            console.log('PAGE 2 DIALOG:', dialog.message());
            await dialog.dismiss();
        });

        // Set standard 800x600 resolution
        await page1.setViewport({ width: 800, height: 600 });
        await page2.setViewport({ width: 800, height: 600 });
        page1.setDefaultTimeout(30000);
        page2.setDefaultTimeout(30000);
        page1.setDefaultNavigationTimeout(30000);
        page2.setDefaultNavigationTimeout(30000);

        // Generate random usernames to avoid collisions
        const suffix = Math.floor(Math.random() * 10000);
        const name1 = `User1_${suffix}`;
        const name2 = `User2_${suffix}`;
        const email1 = `u1_${suffix}@test.com`;
        const email2 = `u2_${suffix}@test.com`;

        console.log(`P1 Username: ${name1}`);
        console.log(`P2 Username: ${name2}`);

        // --- STEP 1: REGISTRATION ---
        console.log('Navigating players to registration pages...');
        await page1.goto('http://pre-alpha/register.php', { waitUntil: 'networkidle2' });
        await page2.goto('http://pre-alpha-var2/register.php', { waitUntil: 'networkidle2' });

        // Accept cookies to hide banner permanently
        console.log('Accepting cookies on both pages...');
        await page1.waitForSelector('#accept-cookies');
        await page1.click('#accept-cookies');
        await page2.waitForSelector('#accept-cookies');
        await page2.click('#accept-cookies');
        await sleep(500);

        // Fill Player 1 Registration
        console.log('Registering Player 1...');
        await page1.waitForSelector('input[name="username"]');
        await page1.type('input[name="username"]', name1);
        await page1.type('input[name="email"]', email1);
        await page1.type('input[name="password"]', 'password123');
        await page1.type('input[name="password_confirm"]', 'password123');
        await page1.click('#legal_consent');
        await page1.screenshot({ path: path.join(screenshotDir, 'm1_p1_reg_form.png') });
        
        // Submit Player 1
        console.log('Submitting Player 1 registration...');
        await page1.waitForSelector('.auth-btn');
        await Promise.all([
            page1.click('.auth-btn'),
            page1.waitForNavigation({ waitUntil: 'networkidle2' })
        ]);

        // Validate P1 redirection to hub
        if (!page1.url().includes('hub.php')) {
            throw new Error(`Player 1 registration failed! Remained at URL: ${page1.url()}`);
        }

        // Fill Player 2 Registration
        console.log('Registering Player 2...');
        await page2.waitForSelector('input[name="username"]');
        await page2.type('input[name="username"]', name2);
        await page2.type('input[name="email"]', email2);
        await page2.type('input[name="password"]', 'password123');
        await page2.type('input[name="password_confirm"]', 'password123');
        await page2.click('#legal_consent');
        await page2.screenshot({ path: path.join(screenshotDir, 'm1_p2_reg_form.png') });
        
        // Submit Player 2
        console.log('Submitting Player 2 registration...');
        await page2.waitForSelector('.auth-btn');
        await Promise.all([
            page2.click('.auth-btn'),
            page2.waitForNavigation({ waitUntil: 'networkidle2' })
        ]);

        // Validate P2 redirection to hub
        if (!page2.url().includes('hub.php')) {
            throw new Error(`Player 2 registration failed! Remained at URL: ${page2.url()}`);
        }

        console.log('Both players registered and redirected to hub.php successfully!');

        // --- STEP 2: LOBBY CREATION (P1) ---
        console.log('Player 1 is creating a lobby...');
        await page1.waitForSelector('input[name="lobby_name"]');
        await page1.type('input[name="lobby_name"]', `Lobby_${suffix}`);
        
        // Use evaluate to set value and fire input events for maximum robustness
        await page1.$eval('input[name="players"]', el => {
            el.value = '2';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page1.screenshot({ path: path.join(screenshotDir, 'm2_p1_hub.png') });

        // Click create button
        await Promise.all([
            page1.waitForNavigation({ waitUntil: 'networkidle2' }),
            page1.evaluate(() => {
                const btn = Array.from(document.querySelectorAll('.btn-game[type="submit"]')).find(el => el.offsetWidth > 0 && el.offsetHeight > 0);
                if (btn) btn.click();
                else throw new Error('No visible lobby creation submit button found!');
            })
        ]);

        const lobbyUrl = page1.url();
        console.log(`P1 Lobby URL: ${lobbyUrl}`);
        if (!lobbyUrl.includes('lobby.php')) {
            throw new Error(`Failed to create lobby! Current URL: ${lobbyUrl}`);
        }
        const lobbyId = lobbyUrl.split('lobby_id=')[1];
        console.log(`Lobby ID successfully extracted: ${lobbyId}`);

        // --- STEP 3: LOBBY JOINING (P2) ---
        console.log('Player 2 is joining the lobby...');
        // Refresh lobbies in P2
        const refreshBtn = await page2.waitForSelector('button[onclick="updateLobbies()"]');
        await refreshBtn.click();
        await sleep(1500); // Wait for list to load

        // Select the lobby radio button via evaluate (X-browser click)
        await page2.evaluate((lid) => {
            const radio = document.querySelector(`input[value="${lid}"]`);
            if (radio) {
                radio.click();
            } else {
                throw new Error(`Lobby radio button for ID ${lid} not found!`);
            }
        }, lobbyId);
        await page2.screenshot({ path: path.join(screenshotDir, 'm3_p2_hub_selected.png') });

        // Click join button
        const joinBtn = await page2.waitForSelector('button[onclick="joinLobby()"]');
        await Promise.all([
            joinBtn.click(),
            page2.waitForNavigation({ waitUntil: 'networkidle2' })
        ]);
        console.log(`P2 joined. URL: ${page2.url()}`);

        // --- STEP 4: WEBSOCKET SYNC & READY ---
        console.log('Waiting for both players to connect to lobby WebSocket...');
        await page1.waitForFunction(() => typeof socketClient !== 'undefined' && socketClient && socketClient.isConnected(), { timeout: 10000 });
        await page2.waitForFunction(() => typeof socketClient !== 'undefined' && socketClient && socketClient.isConnected(), { timeout: 10000 });
        console.log('Both players successfully connected to lobby WebSocket!');

        await page1.screenshot({ path: path.join(screenshotDir, 'm4_p1_lobby.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm4_p2_lobby.png') });

        console.log('Player 2 clicking Ready...');
        const readyBtn = await page2.waitForSelector('#ready-btn');
        await readyBtn.click();

        // Wait for Player 1 to see Player 2 as ready (start button enabled)
        console.log('Waiting for host (Player 1) start button to enable...');
        const startBtn = await page1.waitForSelector('#start-btn:not([disabled])', { timeout: 10000 });

        await page1.screenshot({ path: path.join(screenshotDir, 'm5_p1_lobby_ready.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm5_p2_lobby_ready.png') });

        console.log('Player 1 (Host) clicks Start Game...');
        await Promise.all([
            page1.waitForNavigation({ waitUntil: 'networkidle2' }),
            page2.waitForNavigation({ waitUntil: 'networkidle2' }),
            startBtn.click()
        ]);
        console.log('Redirection to game.php successful!');

        // --- STEP 5: WEBSOCKET SYNC IN GAME ---
        console.log('Waiting for both players to connect to game WebSocket...');
        await page1.waitForFunction(() => typeof socketClient !== 'undefined' && socketClient && socketClient.isConnected(), { timeout: 10000 });
        await page2.waitForFunction(() => typeof socketClient !== 'undefined' && socketClient && socketClient.isConnected(), { timeout: 10000 });
        console.log('Both players successfully connected to game WebSocket!');

        await page1.screenshot({ path: path.join(screenshotDir, 'm6_p1_game_start.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm6_p2_game_start.png') });

        // --- STEP 6: SELECT THEME (RESPONSIBLE PLAYER) ---
        // Dynamically find which player is responsible
        const isP1Responsible = await page1.evaluate(() => {
            const el = document.getElementById('state-topic');
            return el && el.classList.contains('active');
        });

        const respPage = isP1Responsible ? page1 : page2;
        const otherPage = isP1Responsible ? page2 : page1;
        const respName = isP1Responsible ? 'Player 1' : 'Player 2';
        const respImgPrefix = isP1Responsible ? 'p1' : 'p2';

        console.log(`${respName} is responsible for selecting the topic. Entering topic...`);
        await respPage.waitForSelector('#topic-input');
        await respPage.type('#topic-input', 'Кино');
        await respPage.screenshot({ path: path.join(screenshotDir, `m7_${respImgPrefix}_topic_input.png`) });
        await respPage.evaluate(() => document.getElementById('topic-submit-btn').click());

        console.log('Waiting for Groq AI question generation...');
        await page1.waitForSelector('#state-fake.active');
        await page2.waitForSelector('#state-fake.active');
        
        await page1.screenshot({ path: path.join(screenshotDir, 'm8_p1_game_question.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm8_p2_game_question.png') });

        // --- STEP 7: SUBMIT FAKE ANSWERS ---
        console.log('Submitting fake answers...');
        await page1.waitForSelector('#fake-input');
        await page1.type('#fake-input', 'Звездные Войны 10');
        await page1.evaluate(() => document.getElementById('fake-submit-btn').click());

        await page2.waitForSelector('#fake-input');
        await page2.type('#fake-input', 'Властелин Колец 4');
        await page2.evaluate(() => document.getElementById('fake-submit-btn').click());

        console.log('Waiting for players to submit fakes and transit to voting...');
        await page1.waitForSelector('#state-answer.active');
        await page2.waitForSelector('#state-answer.active');

        await page1.screenshot({ path: path.join(screenshotDir, 'm9_p1_fake_submitted.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm9_p2_fake_submitted.png') });

        // --- STEP 8: VOTING ---
        console.log('Voting for answers...');
        await page1.waitForSelector('.answer-btn');
        
        // P1 click first option
        const p1Selected = await page1.evaluate(() => {
            const btns = document.querySelectorAll('.answer-btn');
            if (btns.length > 0) {
                btns[0].click();
                return { text: btns[0].textContent, hasSelected: btns[0].classList.contains('selected') };
            }
            return null;
        });
        console.log('Player 1 selection details:', p1Selected);
        if (!p1Selected || !p1Selected.hasSelected) {
            throw new Error('Player 1 failed to select an option!');
        }
        await page1.screenshot({ path: path.join(screenshotDir, 'm10_p1_voting.png') });
        await page1.evaluate(() => document.getElementById('vote-submit-btn').click());

        await page2.waitForSelector('.answer-btn');
        // P2 click first option
        const p2Selected = await page2.evaluate(() => {
            const btns = document.querySelectorAll('.answer-btn');
            if (btns.length > 0) {
                btns[0].click();
                return { text: btns[0].textContent, hasSelected: btns[0].classList.contains('selected') };
            }
            return null;
        });
        console.log('Player 2 selection details:', p2Selected);
        if (!p2Selected || !p2Selected.hasSelected) {
            throw new Error('Player 2 failed to select an option!');
        }
        await page2.screenshot({ path: path.join(screenshotDir, 'm10_p2_voting.png') });
        await page2.evaluate(() => document.getElementById('vote-submit-btn').click());

        console.log('Waiting for voting results...');
        await page1.waitForSelector('#state-results.active');
        await page2.waitForSelector('#state-results.active');

        // --- STEP 9: RESULTS PANEL ---
        await page1.screenshot({ path: path.join(screenshotDir, 'm11_p1_results.png') });
        await page2.screenshot({ path: path.join(screenshotDir, 'm11_p2_results.png') });

        console.log('=== MULTIPLAYER TEST SUCCESSFUL ===');

    } catch (e) {
        console.error('Error during gameplay simulation:', e);
        if (page1) {
            console.log('Page 1 URL at error:', page1.url());
            try {
                const html1 = await page1.content();
                fs.writeFileSync(path.join(screenshotDir, 'error_p1.html'), html1);
                console.log('Page 1 full HTML written to error_p1.html');
            } catch (err) {
                console.log('Could not get Page 1 content:', err.message);
            }
            await page1.screenshot({ path: path.join(screenshotDir, 'error_p1.png') });
        }
        if (page2) {
            console.log('Page 2 URL at error:', page2.url());
            try {
                const html2 = await page2.content();
                fs.writeFileSync(path.join(screenshotDir, 'error_p2.html'), html2);
                console.log('Page 2 full HTML written to error_p2.html');
            } catch (err) {
                console.log('Could not get Page 2 content:', err.message);
            }
            await page2.screenshot({ path: path.join(screenshotDir, 'error_p2.png') });
        }
    } finally {
        await browser.close();
    }
})();
