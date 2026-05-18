import { chromium } from 'playwright';
import { pathToFileURL } from 'node:url';

const [, , inputPath, outputPath, paperSize = 'A4'] = process.argv;

if (!inputPath || !outputPath) {
    console.error('Usage: node scripts/render-pdf.mjs input.html output.pdf [A4|60mm]');
    process.exit(2);
}

const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
    const page = await browser.newPage({
        viewport: paperSize === '60mm'
            ? { width: 227, height: 1200 }
            : { width: 1024, height: 1448 },
    });

    await page.goto(pathToFileURL(inputPath).href, { waitUntil: 'networkidle' });
    await page.emulateMedia({ media: 'screen' });

    const options = {
        path: outputPath,
        printBackground: true,
        preferCSSPageSize: true,
        margin: paperSize === '60mm'
            ? { top: '2mm', right: '2mm', bottom: '2mm', left: '2mm' }
            : { top: '0mm', right: '0mm', bottom: '0mm', left: '0mm' },
    };

    if (paperSize === '60mm') {
        const heightPx = await page.evaluate(() => {
            const body = document.body;
            const html = document.documentElement;
            return Math.max(
                body.scrollHeight,
                body.offsetHeight,
                html.clientHeight,
                html.scrollHeight,
                html.offsetHeight
            );
        });
        const heightMm = Math.max(120, Math.ceil(heightPx * 0.264583) + 8);
        options.width = '60mm';
        options.height = `${heightMm}mm`;
        options.preferCSSPageSize = false;
    } else {
        options.format = paperSize;
    }

    await page.pdf(options);
} finally {
    await browser.close();
}
