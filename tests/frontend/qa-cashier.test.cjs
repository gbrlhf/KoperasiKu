// Regression of the production Blade inline JS, using a fake DOM and HTTP transport.
// Run from project root: node --test tests/frontend/qa-cashier.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../../resources/views/cashier/pos.blade.php'),'utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
const product = {id:'product-a',sku:'MRG-001',name:'Margarin',sell_price:9500,available:2};
const order = {id:'order-a',pickup_code:'PKP-39763',member_name:'Ahmad',member_id:'EMP-1',items_summary:'Biskuit (1)',total_amount:11000,payment_method:'CREDIT',status:'PENDING_PICKUP',can_handover:true};
function harness() {
  const els=new Map(), calls=[], notices=[], modals=[]; let reply;
  const element = id => {
    if (!els.has(id)) { const events=new Map(); const classes=new Set(); els.set(id,{id,value:'',checked:false,disabled:false,textContent:'',style:{},classList:{add:(...v)=>v.forEach(x=>classes.add(x)),remove:(...v)=>v.forEach(x=>classes.delete(x)),contains:x=>classes.has(x),toggle:(x,on)=>on?classes.add(x):classes.delete(x)},addEventListener:(n,fn)=>events.set(n,fn),fire:n=>events.get(n)?.(),querySelector:()=>null}); }
    return els.get(id);
  };
  const modal = el => ({show(){modals.push(el.id)},hide(){el.fire('hidden.bs.modal')}});
  const ctx=vm.createContext({console,document:{getElementById:element,querySelector:()=>({content:'csrf'}),querySelectorAll:()=>[],addEventListener(){}},window:{addEventListener(){}},bootstrap:{Modal:{getOrCreateInstance:modal,getInstance:modal},Tab:{getOrCreateInstance:()=>({show(){}})}},location:{assign(){}},setTimeout(){},clearTimeout(){},fetch:async(url,opt)=>{calls.push({url,opt}); return await reply(url,opt)},notices});
  const js=source.replace('@js($stockItems)',JSON.stringify([product,{...product,id:'product-b',sku:'MRG-002'}])).replace('@js($pickupQueue)','[]').replace('@js($membersDirectory)','[]');
  vm.runInContext(js,ctx);
  vm.runInContext("renderPosCart = () => {}; showScanToast = (a,b) => notices.push([a,b]); dismissGopayScanSheet = () => {};",ctx);
  return {run:s=>vm.runInContext(s,ctx),element,calls,notices,modals,respond:f=>reply=f,cart:()=>JSON.parse(vm.runInContext('JSON.stringify(posCart)',ctx))};
}
const response=(data,ok=true)=>({ok,json:async()=>data});
test('unknown pickup never becomes a fictitious product',async()=>{const h=harness();h.respond(()=>response({success:false,message:'Kode tidak ditemukan'},false));await h.run("handleIncomingBarcode('PKP-99999')");assert.equal(h.calls[0].url,'/cashier/pickup-lookup?code=PKP-99999');assert.deepEqual(h.cart(),[]);assert.match(h.notices[0][1],/tidak ditemukan/);});
test('unknown barcode is rejected without mutating cart',async()=>{const h=harness();h.respond(()=>response({success:false,message:'Barang tidak ditemukan'},false));await h.run("handleIncomingBarcode('UNKNOWN')");assert.deepEqual(h.cart(),[]);assert.match(h.calls[0].url,/products\/lookup/);});
test('catalogue uses SKU identity and caps available stock',()=>{const h=harness();h.run("posAddToCart('fake',1,'MRG-001');posAddToCart('fake',1,'MRG-001');posAddToCart('fake',1,'MRG-001');posAddToCart('fake',1,'MRG-002')");assert.deepEqual(h.cart().map(x=>[x.product_id,x.price,x.qty]),[['product-a',9500,2],['product-b',9500,1]]);});
test('live pickup opens handover even when initial queue is empty',async()=>{const h=harness();h.respond(()=>response({success:true,data:order}));await h.run("handleIncomingBarcode('PKP-39763')");assert.ok(h.modals.includes('pickupConfirmModal'));assert.deepEqual(h.cart(),[]);assert.equal(h.element('pickupCodeInput').value,'');});
test('closed pickup never opens handover or creates POS items',async()=>{const h=harness();h.respond(()=>response({success:true,data:{...order,status:'COMPLETED'}}));await h.run("handleIncomingBarcode('PKP-39763')");assert.deepEqual(h.modals,[]);assert.deepEqual(h.cart(),[]);});
test('unpaid cash pickup opens real payment form',async()=>{const h=harness();h.respond(()=>response({success:true,data:{...order,status:'PENDING_PAYMENT',can_handover:false,payment_method:'CASH',remaining_amount:11000}}));await h.run("handleIncomingBarcode('PKP-39763')");assert.ok(h.modals.includes('queuedPaymentModal'));assert.equal(h.element('queuedPaymentAmount').value,11000);});
test('handover requires matching code and physical checkbox; successful API opens confirmation',async()=>{const h=harness();h.respond(()=>response({success:true,data:order}));await h.run("handleIncomingBarcode('PKP-39763')");h.element('pickupCodeInput').value='WRONG';await h.run('executePickupHandover()');assert.equal(h.calls.length,1);h.element('pickupCodeInput').value=order.pickup_code;await h.run('executePickupHandover()');assert.equal(h.calls.length,1);h.element('checkPhysicalHandover').checked=true;await h.run('executePickupHandover()');assert.equal(h.calls[1].url,'/api/v1/orders/order-a/confirm-pickup');const p=JSON.parse(h.calls[1].opt.body);assert.equal(p.pickup_code,order.pickup_code);assert.equal(p.handover_confirmed,true);assert.ok(p.idempotency_key);assert.ok(h.modals.includes('pickupSuccessModal'));});
test('failed handover reports error and preserves retry key',async()=>{const h=harness();h.respond(()=>response({success:true,data:order}));await h.run("handleIncomingBarcode('PKP-39763')");h.element('pickupCodeInput').value=order.pickup_code;h.element('checkPhysicalHandover').checked=true;h.respond(()=>response({success:false,message:'Stok konflik'},false));await h.run('executePickupHandover()');assert.equal(h.element('pickupError').textContent,'Stok konflik');assert.equal(h.element('btnExecuteHandover').disabled,false);await h.run('executePickupHandover()');assert.equal(JSON.parse(h.calls[1].opt.body).idempotency_key,JSON.parse(h.calls[2].opt.body).idempotency_key);});
