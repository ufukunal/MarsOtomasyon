import type { ApiResponse } from "./api-client";
import { ApiClientError } from "./api-client";
import { createButton, createField, createGrid, createTabs } from "./ui/components";

export interface WarehouseApi {
  get<T>(path: string, signal?: AbortSignal): Promise<ApiResponse<T>>;
  request<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>>;
}

interface WorkItem {
  publicId: string;
  number: string;
  kind: string;
  state: string;
  warehousePublicId: string;
  version: number;
  createdAt: string;
}

interface ReceivingItem {
  goodsReceiptPublicId: string;
  goodsReceiptNumber: string;
  purchaseOrderPublicId: string;
  goodsReceiptLinePublicId: string;
  productPublicId: string;
  variantPublicId: string | null;
  uomPublicId: string;
  warehousePublicId: string;
  locationPublicId: string | null;
  lotPublicId: string | null;
  serialPublicId: string | null;
  quantity: number;
  remainingQuarantineQuantity: number;
  postedAt: string;
}

interface ReservationItem {
  publicId: string;
  salesOrderPublicId: string;
  salesOrderLinePublicId: string;
  warehousePublicId: string;
  currentBaseQuantity: number;
}

interface OfflineItem {
  publicId: string;
  clientOperationId: string;
  warehousePublicId: string;
  operationType: string;
  state: string;
  conflictCode: string | null;
}

export function createWarehousePage(
  api: WarehouseApi,
  operationKey: () => string = () => crypto.randomUUID(),
  initialTabId?: string): HTMLElement
{
  const root=document.createElement("section");
  root.className="mars-foundation-panel mars-component-stack";

  const h=document.createElement("h1");
  h.textContent="Stok ve Depo · Operasyon";

  const authority=document.createElement("p");
  authority.textContent=
    "Inventory Ledger fiziksel miktar otoritesidir. Reservation non-physical commitment'tır. " +
    "Pick / Pack / Stage / Load stok post etmez; Dispatch POST Sales-owned, Goods Receipt POST Purchasing-owned kalır.";

  const status=document.createElement("p");
  status.setAttribute("role","status");
  status.setAttribute("aria-live","polite");

  const receivingGrid=workReceivingGrid();
  const reservationGrid=simpleGrid<ReservationItem>("Reservations — commitment / fiziksel stok değil",[
    ["order","Sales Order",x=>x.salesOrderPublicId],
    ["line","Order line",x=>x.salesOrderLinePublicId],
    ["warehouse","Warehouse",x=>x.warehousePublicId],
    ["qty","Remaining base qty",x=>x.currentBaseQuantity]
  ]);
  const pickGrid=workGrid("Pick work — STOCK = NONE");
  const transferGrid=workGrid("Warehouse Transfers — unresolved TRANSIT görünür olmalı");
  const countGrid=workGrid("Stock Counts — Set Stock yok; delta adjustment");
  const scrapGrid=workGrid("Damage / Scrap — physical OUT, Finance write-off yok");
  const putawayGrid=workGrid("Put-away / Replenishment — internal move, net qty 0");
  const offlineGrid=simpleGrid<OfflineItem>("Offline / scan operation conflicts",[
    ["client","Client operation",x=>x.clientOperationId],
    ["type","Type",x=>x.operationType],
    ["state","State",x=>x.state],
    ["conflict","Conflict",x=>x.conflictCode??""]
  ]);
  const trace=document.createElement("pre");
  trace.className="mars-proof-output";

  const dispositionPanel=buildDispositionPanel();
  const pickPanel=buildPickPanel();
  const transferPanel=buildTransferPanel();
  const countPanel=buildCountPanel();
  const scrapPanel=buildScrapPanel();
  const packagePanel=buildPackagePanel();
  const offlinePanel=buildOfflinePanel();

  const tabs=createTabs([
    {id:"warehouse-receiving",label:"Receiving / QUARANTINE",panel:panel([boundary("Goods Receipt POST Purchasing-owned; bu ekran ikinci STOCK IN üretmez."),receivingGrid.element])},
    {id:"warehouse-putaway",label:"Disposition / Put-away",panel:panel([boundary("Release disposition ve put-away ayrı, auditable fiziksel etkilerdir."),dispositionPanel,putawayGrid.element])},
    {id:"warehouse-reservations",label:"Reservations",panel:panel([boundary("Reservation = commitment; physical disposition değildir."),reservationGrid.element])},
    {id:"warehouse-picking",label:"Picking",panel:panel([boundary("AVAILABLE-only; FEFO/FIFO; Pick STOCK = NONE."),pickPanel,pickGrid.element])},
    {id:"warehouse-pack",label:"Pack / Stage / Load",panel:panel([boundary("Pack / Stage / Load STOCK = NONE. Dispatch POST yalnız Sales authority'dir."),packagePanel])},
    {id:"warehouse-transfer",label:"Transfers",panel:panel([boundary("ISSUE → TRANSIT → partial/full RECEIVE; company total normal transferde korunur."),transferPanel,transferGrid.element])},
    {id:"warehouse-count",label:"Stock Counts",panel:panel([boundary("İlk sayım blind; snapshot + intervening movements. Positive adjustment Finance valuation gelene kadar BLOCK."),countPanel,countGrid.element])},
    {id:"warehouse-scrap",label:"Damage / Scrap",panel:panel([boundary("Scrap eligible non-available quantityden physical OUT; accounting/write-off Finance-owned."),scrapPanel,scrapGrid.element])},
    {id:"warehouse-trace",label:"Trace",panel:panel([boundary("Ledger trace read-only; mutable current-stock alanı yok."),trace])},
    {id:"warehouse-offline",label:"Offline / Scan",panel:panel([boundary("Aynı client_operation_id retry idempotent; stale conflict sessiz overwrite edilmez."),offlinePanel,offlineGrid.element])}
  ]);

  if(initialTabId) tabs.activate(initialTabId);
  root.append(h,authority,status,tabs.element);
  queueMicrotask(()=>{void refreshAll();});
  return root;

  function buildDispositionPanel(): HTMLElement {
    const product=f("wh-disposition-product","Product Public ID");
    const uom=f("wh-disposition-uom","UOM Public ID");
    const warehouse=f("wh-disposition-warehouse","Warehouse Public ID");
    const location=f("wh-disposition-location","Location Public ID");
    const qty=f("wh-disposition-qty","Quantity"); qty.input.type="number"; qty.input.value="1";
    const conversion=f("wh-disposition-conv","Conversion"); conversion.input.type="number"; conversion.input.value="1";
    const source=f("wh-disposition-source","Source disposition (2=QUARANTINE, 3=QUALITY_HOLD, 4=REWORK, 5=DAMAGED)"); source.input.type="number"; source.input.value="2";
    const target=f("wh-disposition-target","Target disposition (1=AVAILABLE etc.)"); target.input.type="number"; target.input.value="1";
    const reason=f("wh-disposition-reason","Reason");
    const change=createButton({label:"Disposition uygula",variant:"primary",onClick:()=>void mutate("/warehouse/dispositions",{
      productPublicId:product.input.value.trim(),variantPublicId:null,uomPublicId:uom.input.value.trim(),
      quantity:num(qty.input.value),conversionFactorSnapshot:num(conversion.input.value),
      source:pos(warehouse.input.value,location.input.value,num(source.input.value)),
      target:pos(warehouse.input.value,location.input.value,num(target.input.value)),
      reason:reason.input.value.trim(),sourceDocumentPublicId:null,sourceLinePublicId:null
    },"Disposition Inventory authority üzerinden işlendi.")});
    const targetLocation=f("wh-putaway-target","Put-away target Location Public ID");
    const putaway=createButton({label:"Put-away",onClick:()=>void mutate("/warehouse/putaway",{
      productPublicId:product.input.value.trim(),variantPublicId:null,uomPublicId:uom.input.value.trim(),
      quantity:num(qty.input.value),conversionFactorSnapshot:num(conversion.input.value),
      source:pos(warehouse.input.value,location.input.value,num(target.input.value)),
      target:pos(warehouse.input.value,targetLocation.input.value,num(target.input.value)),
      sourceDocumentPublicId:null,sourceLinePublicId:null
    },"Put-away internal movement tamamlandı.")});
    const replenish=createButton({label:"Manual replenishment",onClick:()=>void mutate("/warehouse/replenishment",{
      productPublicId:product.input.value.trim(),variantPublicId:null,uomPublicId:uom.input.value.trim(),
      quantity:num(qty.input.value),conversionFactorSnapshot:num(conversion.input.value),
      source:pos(warehouse.input.value,location.input.value,1),
      target:pos(warehouse.input.value,targetLocation.input.value,1),
      sourceDocumentPublicId:null,sourceLinePublicId:null
    },"Replenishment internal movement tamamlandı.")});
    return panel([product.element,uom.element,warehouse.element,location.element,targetLocation.element,qty.element,conversion.element,source.element,target.element,reason.element,change,putaway,replenish]);
  }

  function buildPickPanel(): HTMLElement {
    const dispatch=f("wh-pick-dispatch","Dispatch Public ID");
    const version=f("wh-pick-version","Dispatch version"); version.input.type="number";
    const line=f("wh-pick-line","Dispatch line Public ID");
    const warehouse=f("wh-pick-warehouse","Warehouse Public ID");
    const location=f("wh-pick-location","Location Public ID");
    const lot=f("wh-pick-lot","Lot Public ID (optional)");
    const serial=f("wh-pick-serial","Serial Public ID (optional)");
    const qty=f("wh-pick-qty","Qty"); qty.input.type="number";qty.input.value="1";
    const reason=f("wh-pick-override","Override reason (empty = default FEFO/FIFO)");
    const recommend=createButton({label:"FEFO/FIFO önerisini getir",onClick:async()=>{
      try{
        const r=await api.get<unknown[]>("/warehouse/picks/recommendation?dispatchPublicId="+encodeURIComponent(dispatch.input.value.trim())+"&dispatchLinePublicId="+encodeURIComponent(line.input.value.trim()));
        set("Recommendation: "+JSON.stringify(r.data));
      }catch(e){set(msg(e));}
    }});
    const pick=createButton({label:"Pick kaydet — stok post etmez",variant:"primary",onClick:()=>void mutate("/warehouse/picks",{
      dispatchPublicId:dispatch.input.value.trim(),dispatchExpectedVersion:num(version.input.value),dispatchLinePublicId:line.input.value.trim(),
      warehousePublicId:warehouse.input.value.trim(),quantity:num(qty.input.value),locationPublicId:location.input.value.trim(),
      lotPublicId:optional(lot.input.value),serialPublicId:optional(serial.input.value),
      strategyOverride:!!reason.input.value.trim(),overrideReason:optional(reason.input.value)
    },"Pick work kaydedildi; STOCK etkisi yok.")});
    return panel([dispatch.element,version.element,line.element,warehouse.element,location.element,lot.element,serial.element,qty.element,reason.element,recommend,pick]);
  }

  function buildPackagePanel(): HTMLElement {
    const dispatch=f("wh-package-dispatch","Dispatch Public ID");
    const warehouse=f("wh-package-warehouse","Warehouse Public ID");
    const line=f("wh-package-line","Dispatch line Public ID");
    const code=f("wh-package-code","Package code");
    const qty=f("wh-package-qty","Packed qty");qty.input.type="number";qty.input.value="1";
    const packageId=f("wh-package-id","Package Public ID for stage/load");
    const create=createButton({label:"Package oluştur",variant:"primary",onClick:()=>void mutate("/warehouse/packages",{
      packageCode:code.input.value.trim(),dispatchPublicId:dispatch.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),
      items:[{dispatchLinePublicId:line.input.value.trim(),quantity:num(qty.input.value),lotPublicId:null,serialPublicId:null}],
      trackingReference:null,carrierReference:null
    },"Package PACKED kaydedildi.")});
    const stage=createButton({label:"Stage",onClick:()=>void mutate("/warehouse/staging",{
      dispatchPublicId:dispatch.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),packagePublicIds:[packageId.input.value.trim()]
    },"Package STAGED.")});
    const load=createButton({label:"Load",onClick:()=>void mutate("/warehouse/loading",{
      dispatchPublicId:dispatch.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),packagePublicIds:[packageId.input.value.trim()]
    },"Package LOADED; STOCK etkisi yok.")});
    return panel([dispatch.element,warehouse.element,line.element,code.element,qty.element,packageId.element,create,stage,load]);
  }

  function buildTransferPanel(): HTMLElement {
    const number=f("wh-transfer-number","Transfer no");
    const sourceWh=f("wh-transfer-source-wh","Source Warehouse Public ID");
    const targetWh=f("wh-transfer-target-wh","Target Warehouse Public ID");
    const sourceLoc=f("wh-transfer-source-loc","Source Location Public ID");
    const targetLoc=f("wh-transfer-target-loc","Target Location Public ID");
    const product=f("wh-transfer-product","Product Public ID");
    const uom=f("wh-transfer-uom","UOM Public ID");
    const qty=f("wh-transfer-qty","Qty");qty.input.type="number";qty.input.value="1";
    const transfer=f("wh-transfer-id","Transfer Public ID");
    const line=f("wh-transfer-line","Transfer line Public ID");
    const version=f("wh-transfer-version","Version");version.input.type="number";version.input.value="1";
    const create=createButton({label:"Transfer DRAFT oluştur",variant:"primary",onClick:()=>void mutate("/warehouse/transfers",{
      number:number.input.value.trim(),sourceWarehousePublicId:sourceWh.input.value.trim(),targetWarehousePublicId:targetWh.input.value.trim(),
      lines:[{sequence:1,productPublicId:product.input.value.trim(),variantPublicId:null,uomPublicId:uom.input.value.trim(),quantity:num(qty.input.value),
        sourceLocationPublicId:optional(sourceLoc.input.value),targetLocationPublicId:targetLoc.input.value.trim(),lotPublicId:null,serialPublicId:null}]
    },"Transfer DRAFT oluşturuldu.")});
    const issue=createButton({label:"ISSUE → TRANSIT",onClick:()=>void mutate("/warehouse/transfers/"+transfer.input.value.trim()+"/issue",{version:num(version.input.value)},"Transfer ISSUED; company net qty 0.")});
    const receive=createButton({label:"Partial / full RECEIVE",onClick:()=>void mutate("/warehouse/transfers/"+transfer.input.value.trim()+"/receive",{
      version:num(version.input.value),lines:[{transferLinePublicId:line.input.value.trim(),quantity:num(qty.input.value),targetLocationPublicId:targetLoc.input.value.trim(),disposition:1}]
    },"Transfer receive işlendi; unresolved remainder TRANSIT kalır.")});
    const close=createButton({label:"Close",onClick:()=>void mutate("/warehouse/transfers/"+transfer.input.value.trim()+"/close",{version:num(version.input.value)},"Transfer CLOSED.")});
    const reverse=createButton({label:"Compensating reverse",onClick:()=>void mutate("/warehouse/transfers/"+transfer.input.value.trim()+"/reverse",{version:num(version.input.value)},"Transfer reversal kaydedildi.")});
    return panel([number.element,sourceWh.element,targetWh.element,sourceLoc.element,targetLoc.element,product.element,uom.element,qty.element,transfer.element,line.element,version.element,create,issue,receive,close,reverse]);
  }

  function buildCountPanel(): HTMLElement {
    const number=f("wh-count-number","Count no");
    const warehouse=f("wh-count-wh","Warehouse Public ID");
    const location=f("wh-count-location","Location Public ID");
    const id=f("wh-count-id","Count Public ID");
    const line=f("wh-count-line","Count line Public ID");
    const version=f("wh-count-version","Version");version.input.type="number";version.input.value="1";
    const counted=f("wh-count-value","Blind counted qty");counted.input.type="number";counted.input.value="0";
    const create=createButton({label:"Count DRAFT oluştur",onClick:()=>void mutate("/warehouse/counts",{
      number:number.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),locationPublicIds:[location.input.value.trim()]
    },"Count DRAFT oluşturuldu.")});
    const start=createButton({label:"Start snapshot / blind count",onClick:()=>void mutate("/warehouse/counts/"+id.input.value.trim()+"/start",{version:num(version.input.value)},"Count COUNTING; snapshot boundary captured.")});
    const observe=createButton({label:"Blind observation kaydet",onClick:()=>void mutate("/warehouse/counts/"+id.input.value.trim()+"/observations",{
      version:num(version.input.value),isRecount:false,observations:[{countLinePublicId:line.input.value.trim(),countedQuantity:num(counted.input.value)}]
    },"Physical observation kaydedildi.")});
    const review=createButton({label:"Review snapshot + intervening",onClick:()=>void mutate("/warehouse/counts/"+id.input.value.trim()+"/review",{version:num(version.input.value)},"Count reconciliation reviewed.")});
    const post=createButton({label:"Post discrepancy delta",variant:"primary",onClick:()=>void mutate("/warehouse/counts/"+id.input.value.trim()+"/post",{},"Negative/zero eligible delta posted. Positive adjustment valuation yoksa BLOCK.")});
    return panel([number.element,warehouse.element,location.element,id.element,line.element,version.element,counted.element,create,start,observe,review,post]);
  }

  function buildScrapPanel(): HTMLElement {
    const number=f("wh-scrap-number","Scrap no");
    const warehouse=f("wh-scrap-wh","Warehouse Public ID");
    const location=f("wh-scrap-loc","Location Public ID");
    const product=f("wh-scrap-product","Product Public ID");
    const uom=f("wh-scrap-uom","UOM Public ID");
    const qty=f("wh-scrap-qty","Qty");qty.input.type="number";qty.input.value="1";
    const disposition=f("wh-scrap-disp","Source disposition (3/4/5)");disposition.input.type="number";disposition.input.value="5";
    const reason=f("wh-scrap-reason","Reason");
    const id=f("wh-scrap-id","Scrap Public ID");
    const request=createButton({label:"Scrap request",onClick:()=>void mutate("/warehouse/scrap",{
      number:number.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),productPublicId:product.input.value.trim(),variantPublicId:null,
      uomPublicId:uom.input.value.trim(),quantity:num(qty.input.value),conversionFactorSnapshot:1,
      source:pos(warehouse.input.value,location.input.value,num(disposition.input.value)),reason:reason.input.value.trim()
    },"Scrap PENDING_APPROVAL.")});
    const post=createButton({label:"Approved scrap POST",variant:"primary",onClick:()=>void mutate("/warehouse/scrap/"+id.input.value.trim()+"/post",{},"Scrap physical OUT posted; Finance write-off yok.")});
    return panel([number.element,warehouse.element,location.element,product.element,uom.element,qty.element,disposition.element,reason.element,id.element,request,post]);
  }

  function buildOfflinePanel(): HTMLElement {
    const client=f("wh-offline-client","client_operation_id");
    const warehouse=f("wh-offline-wh","Warehouse Public ID");
    const type=f("wh-offline-type","Operation type");
    const scan=f("wh-offline-scan","Scan identity");
    const record=createButton({label:"Offline operation journal",onClick:()=>void mutate("/warehouse/offline-operations",{
      clientOperationId:client.input.value.trim(),warehousePublicId:warehouse.input.value.trim(),operationType:type.input.value.trim(),
      workPublicId:null,expectedVersion:null,scanIdentity:scan.input.value.trim(),localTimestamp:new Date().toISOString()
    },"Offline operation kaydedildi / duplicate retry idempotent.")});
    return panel([client.element,warehouse.element,type.element,scan.element,record]);
  }

  async function refreshAll(): Promise<void> {
    await Promise.all([
      load("/warehouse/receiving",receivingGrid),
      load("/warehouse/putaway",putawayGrid),
      load("/warehouse/reservations",reservationGrid),
      load("/warehouse/picks",pickGrid),
      load("/warehouse/transfers",transferGrid),
      load("/warehouse/counts",countGrid),
      load("/warehouse/scrap",scrapGrid),
      load("/warehouse/offline-operations",offlineGrid),
      loadTrace()
    ]);
  }

  async function load<T>(path:string,target:{setRows:(rows:T[],state?:any)=>void}):Promise<void>{
    try{
      target.setRows([],{kind:"loading"});
      const r=await api.get<T[]>(path);
      target.setRows(r.data,r.data.length?{kind:"ready"}:{kind:"empty",message:"Kayıt yok."});
    }catch(e){target.setRows([],{kind:"error",message:msg(e)});}
  }

  async function loadTrace():Promise<void>{
    try{
      const r=await api.get<unknown[]>("/warehouse/trace");
      trace.textContent=r.data.length?JSON.stringify(r.data,null,2):"Trace hareketi yok.";
    }catch(e){trace.textContent=msg(e);}
  }

  async function mutate(path:string,body:unknown,success:string):Promise<void>{
    try{
      await api.request(path,{method:"POST",headers:{"Content-Type":"application/json","Idempotency-Key":operationKey()},body:JSON.stringify(body)});
      set(success);await refreshAll();
    }catch(e){set(msg(e));}
  }

  function workReceivingGrid(){
    return createGrid<ReceivingItem>({
      caption:"POSTED Goods Receipt → QUARANTINE handoff",
      columns:[
        {key:"receipt",header:"Receipt",value:x=>x.goodsReceiptNumber},
        {key:"product",header:"Product",value:x=>x.productPublicId},
        {key:"warehouse",header:"Warehouse",value:x=>x.warehousePublicId},
        {key:"qty",header:"Received",value:x=>x.quantity,align:"end"},
        {key:"remain",header:"QUARANTINE remaining",value:x=>x.remainingQuarantineQuantity,align:"end"}
      ],rows:[],state:{kind:"loading"}
    });
  }

  function workGrid(caption:string){
    return createGrid<WorkItem>({
      caption,
      columns:[
        {key:"number",header:"No / source",value:x=>x.number},
        {key:"kind",header:"Kind",value:x=>x.kind},
        {key:"state",header:"State",value:x=>x.state},
        {key:"warehouse",header:"Warehouse",value:x=>x.warehousePublicId},
        {key:"version",header:"Version",value:x=>x.version,align:"end"}
      ],rows:[],state:{kind:"loading"}
    });
  }

  function set(value:string){status.textContent=value;}
}

function simpleGrid<T>(caption:string,columns:Array<[string,string,(x:T)=>string|number]>){
  return createGrid<T>({
    caption,
    columns:columns.map(([key,header,value])=>({key,header,value})),
    rows:[],state:{kind:"loading"}
  });
}
function panel(children:HTMLElement[]):HTMLElement{const e=document.createElement("div");e.className="mars-component-stack";e.append(...children);return e;}
function boundary(text:string):HTMLElement{const p=document.createElement("p");p.textContent=text;return p;}
function f(id:string,label:string){return createField({id,label});}
function num(v:string):number{const n=Number(v);return Number.isFinite(n)?n:0;}
function optional(v:string):string|null{const x=v.trim();return x||null;}
function pos(warehouse:string,location:string,disposition:number){return{warehousePublicId:warehouse.trim(),locationPublicId:optional(location),disposition,lotPublicId:null,serialPublicId:null};}
function msg(e:unknown):string{return e instanceof ApiClientError?e.message+(e.correlationId?" / "+e.correlationId:""):e instanceof Error?e.message:"Beklenmeyen hata.";}
