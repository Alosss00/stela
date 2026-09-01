const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
    try {
        const htmlPath = path.resolve('../user_manual.html');
        const pdfPath = path.resolve('../user-manual-stela.pdf');
        const content = fs.readFileSync(htmlPath, 'utf8');

        console.log('Launching browser...');
        const browser = await puppeteer.launch();
        const page = await browser.newPage();
        
        console.log('Setting HTML content...');
        await page.setContent(content, { waitUntil: 'networkidle0' });
        
        console.log('Generating PDF...');
        await page.pdf({
            path: pdfPath,
            format: 'A4',
            printBackground: true
        });

        await browser.close();
        console.log('PDF successfully generated at ' + pdfPath);
    } catch (error) {
        console.error('Error generating PDF:', error);
        process.exit(1);
    }
})();
