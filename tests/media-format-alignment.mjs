import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const root=new URL('../',import.meta.url);
let css='';
for(const path of ['public/css/00_theme.css','public/css/20_base.css','public/css/24_table.css','public/css/50_forms.css','public/css/70_components.css','panels/admin/030_settings/project.css'])css+='\n'+await readFile(new URL(path,root),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
const page=await browser.newPage({viewport:{width:1440,height:700}});
const blank='<span aria-hidden="true">&nbsp;</span>';
await page.setContent(`<style>${css}</style><body class="cms settings"><div class="projectFiles"><section class="widget"><div class="tableScroll"><table class="projectRows"><tbody><tr class="imageFormatRow"><td class="pageSettingDrag"><i>⠿</i></td><td><label class="formatField">${blank}<input value="background"></label></td><td><div class="formatSizing"><label>${blank}<select><option>Megapixels</option></select></label><label><span>MP</span><input type="number" value="3"></label></div></td><td><div class="formatField">${blank}<label class="projectSwitch"><input type="checkbox" class="switch" checked><div><div></div></div></label></div></td><td class="projectRemove"><button>×</button></td></tr></tbody></table></div></section></div></body>`);
const centers=await page.locator('.imageFormatRow').evaluate(row=>{
const middle=e=>{const r=e.getBoundingClientRect();return r.y+r.height/2};
return {row:middle(row),controls:[...row.querySelectorAll('input:not(.switch),select,.projectSwitch,.pageSettingDrag>i,.projectRemove>button')].map(middle)};
});
for(const center of centers.controls)assert.ok(Math.abs(center-centers.row)<3,JSON.stringify(centers));
console.log('ok: media format controls, grip and remove button are vertically centered in the row');
} finally {await browser.close();}
