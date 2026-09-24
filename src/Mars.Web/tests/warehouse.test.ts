import test from "node:test";
import assert from "node:assert/strict";
import { Window } from "happy-dom";

installDom();
const { createWarehousePage } = await import("../src/warehouse.ts");

test("Warehouse Web preserves physical authority and operational boundaries", async () => {
  const gets:string[]=[];
  const api={
    get:async<T>(path:string)=>{gets.push(path);return{data:[] as T,correlationId:"corr-wh",status:200};},
    request:async<T>()=>({data:{} as T,correlationId:"corr-wh",status:200})
  };

  const page=createWarehousePage(api,()=>"wh-op");
  document.body.replaceChildren(page);
  await flush();

  const text=page.textContent??"";
  assert.match(text,/Inventory Ledger/);
  assert.match(text,/Reservation non-physical/);
  assert.match(text,/Pick \/ Pack \/ Stage \/ Load stok post etmez/);
  assert.match(text,/Dispatch POST Sales-owned/);
  assert.match(text,/Goods Receipt POST Purchasing-owned/);
  assert.match(text,/QUARANTINE/);
  assert.match(text,/TRANSIT/);
  assert.match(text,/Positive adjustment Finance valuation/);
  assert.equal(text.includes("Set Stock"),false);
  assert.equal(text.includes("Warehouse Dispatch POST"),false);
  assert.equal(text.includes("Warehouse Goods Receipt POST"),false);

  for(const path of [
    "/warehouse/receiving","/warehouse/putaway","/warehouse/reservations",
    "/warehouse/picks","/warehouse/transfers","/warehouse/counts",
    "/warehouse/scrap","/warehouse/offline-operations","/warehouse/trace"
  ]) assert.ok(gets.includes(path),"Expected GET "+path);
});

async function flush():Promise<void>{
  await Promise.resolve();await Promise.resolve();await new Promise(resolve=>setTimeout(resolve,0));
}

function installDom():void{
  const window=new Window({url:"https://mars.test/warehouse"});
  const globals:Record<string,unknown>={
    window,document:window.document,history:window.history,location:window.location,
    HTMLElement:window.HTMLElement,HTMLButtonElement:window.HTMLButtonElement,
    HTMLInputElement:window.HTMLInputElement,HTMLSelectElement:window.HTMLSelectElement,
    HTMLAnchorElement:window.HTMLAnchorElement,HTMLTableElement:window.HTMLTableElement,
    HTMLTableRowElement:window.HTMLTableRowElement,HTMLTableSectionElement:window.HTMLTableSectionElement,
    Element:window.Element,Node:window.Node,Event:window.Event,MouseEvent:window.MouseEvent,
    KeyboardEvent:window.KeyboardEvent,AbortController,DOMException,customElements:window.customElements
  };
  for(const [name,value] of Object.entries(globals))
    Object.defineProperty(globalThis,name,{configurable:true,writable:true,value});
}
