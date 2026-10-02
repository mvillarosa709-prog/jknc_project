const fs=require('fs'), assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=require('path').resolve(__dirname,'../..')+'/';
const baseline=process.argv.includes('--baseline');
const dir=process.env.MESSAGE_COMPOSER_BASELINE_DIR || '';
if(baseline && !dir) throw new Error('MESSAGE_COMPOSER_BASELINE_DIR must point to the pre-change backup directory.');
const source=fs.readFileSync(baseline?dir+'index.blade.php':root+'resources/views/activities/index.blade.php','utf8');
const view=fs.readFileSync(baseline?dir+'inline-details.blade.php':root+'resources/views/activities/partials/inline-details.blade.php','utf8');
const start=view.indexOf('<section class="mt-4">'),end=view.indexOf('</section>',start)+10;
assert(start>0);
function method(name){const m=source.match(new RegExp('^        (async )?'+name+'\\(([^)]*)\\) \\{\\r?\\n([\\s\\S]*?)^        \\},','m'));assert(m,name);return (m[1]||'')+name+'('+m[2]+'){'+m[3]+'}';}
const names=['loadMessageAttachments','uploadMessageAttachment','messageAttachmentCategory','messageAttachmentName','messageAttachmentSize','isMessageDraftAttachment','removeMessageDraftAttachment','messageDraftAttachments','messageDraftHasSingleMessengerImage','messageDraftCanSendExternally'];
if(!baseline)names.push('isCurrentMessageDraft','selectMessageDraftAttachment');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/api/**',async route=>{
 const url=route.request().url();let result;
 if(route.request().method()==='POST')result={id:99,original_filename:'test.png',safe_url:'data:image/png;base64,iVBORw0KGgo=',mime_type:'image/png',size_bytes:100};
 else result={attachments:[{id:6,original_filename:'older.pdf',safe_url:'/old.pdf',mime_type:'application/pdf',size_bytes:22},{id:99,original_filename:'test.png',safe_url:'data:image/png;base64,iVBORw0KGgo=',mime_type:'image/png',size_bytes:100}]};
 await route.fulfill({json:result});
 });
 await page.route('http://local.test/',r=>r.fulfill({contentType:'text/html',body:'<html><head><meta name="csrf-token" content="test"><style>[x-cloak]{display:none}</style></head><body></body></html>'}));
 await page.goto('http://local.test/');
 await page.evaluate(({markup,methods})=>{
 document.body.innerHTML='<div x-data="fixture()">'+markup.replace(/\{\{--[\s\S]*?--\}\}/g,'')+'</div>';
 window.fixture=new Function('return {selectedMessageDetails:{id:2},showMessageDetails:true,activityDetailRequest:1,messageDraftSession:1,messageAttachmentLoadRequest:0,messageDraftAttachmentIds:[],messageAttachments:[],messageAttachmentLoading:false,messageAttachmentUploading:false,messageSaving:false,messageRequestError:"",newMessageEntry:{body:"CODEX-LOCAL-ATTACHMENT-E2E"},fetchActivities:async function(){},addMessageEntry:function(){},'+methods+'}');
 },{markup:view.slice(start,end),methods:names.map(method).join(',')});
 await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js'});
 await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'});
 await page.waitForFunction(()=>window.Alpine && document.querySelector('[x-data]')._x_dataStack);
 await page.locator('input[type=file]').setInputFiles({name:'test.png',mimeType:'image/png',buffer:Buffer.from('test')});
 await page.waitForTimeout(500);
 const result=await page.evaluate(()=>{const s=Alpine.$data(document.querySelector('[x-data]'));return {ids:[...s.messageDraftAttachmentIds],draft:s.messageAttachments.filter(a=>s.isMessageDraftAttachment(a)).map(a=>a.id),error:s.messageRequestError};});
 console.log(JSON.stringify({baseline,result,errors}));
 if(!baseline){
  await page.getByRole('button',{name:'Remove attachment from reply'}).click();
  await page.getByText('Current reply attachments',{exact:true}).waitFor({state:'hidden'});
  assert.equal(await page.getByText('Current reply attachments',{exact:true}).isVisible(),false);
  await page.getByRole('button',{name:/Conversation Files/}).click();
  await page.getByRole('button',{name:'Use in reply'}).last().click();
  await page.getByText('Current reply attachments',{exact:true}).waitFor({state:'visible'});
  assert.equal(await page.getByText('Current reply attachments',{exact:true}).isVisible(),true);
  console.log('PASS: visible draft card, remove to Conversation Files, reselect into draft.');
 }
 const stale = await page.evaluate(async()=>{
  const s=Alpine.$data(document.querySelector('[x-data]'));
  const original=window.fetch;
  window.fetch=async()=>({ok:true,json:async()=>({attachments:[{id:6,original_filename:'older.pdf'}]})});
  await s.loadMessageAttachments();
  window.fetch=original;
  return s.messageAttachments.filter(a=>s.isMessageDraftAttachment(a)).map(a=>a.id);
 });
 assert.deepEqual(stale,baseline?[]:[99]);
 const late = await page.evaluate(async()=>{
  const s=Alpine.$data(document.querySelector('[x-data]'));
  const original=window.fetch; let finish;
  window.fetch=()=>new Promise(resolve=>finish=resolve);
  const upload=s.uploadMessageAttachment({target:{files:[new File(['test'],'late.png',{type:'image/png'})],value:'test'}});
  s.messageDraftSession++; s.selectedMessageDetails={id:3}; s.messageDraftAttachmentIds=[]; s.messageAttachments=[];
  window.fetch=async()=>({ok:true,json:async()=>({attachments:[]})});
  finish({ok:true,json:async()=>({id:101,original_filename:'late.png'})});
  await upload; window.fetch=original;
  return [...s.messageDraftAttachmentIds];
 });
 assert.deepEqual(late,baseline?[101]:[]);
 console.log(JSON.stringify({baseline,staleSnapshotDraft:stale,lateUploadNewConversationDraft:late}));
 await browser.close();
 assert.deepEqual(result.ids,[99]);assert.deepEqual(result.draft,[99]);assert.equal(errors.length,0);
})().catch(e=>{console.error(e.message);process.exit(1)});
