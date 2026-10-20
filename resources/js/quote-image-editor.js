/**
 * Private, dependency-free canvas editor for quote-only fabrication photographs.
 * Original files stay immutable. Livewire receives a rasterized new version.
 */
const quoteImageEditors = new WeakMap();

function quoteImageEditor(root) {
    if (quoteImageEditors.has(root)) return quoteImageEditors.get(root);
    const canvas = root.querySelector('[data-v65-image-canvas]');
    const overlay = root.querySelector('[data-v65-image-overlay]');
    const ctx = canvas.getContext('2d');
    const over = overlay.getContext('2d');
    const widthInput = root.querySelector('[data-v65-image-width]');
    const status = root.querySelector('[data-v65-image-status]');
    const color = root.querySelector('[data-v65-image-color]');
    const textInput = root.querySelector('[data-v65-image-text]');
    const state = {
        root, canvas, overlay, ctx, over, widthInput, status, color, textInput,
        id: null, item: null, mode: 'select', history: [], cursor: -1, steps: [],
        objects: [], selected: null, operations: [], baseIndex: -1, drag: null, preview: null, nextId: 1,
        rect: null, start: null, moving: false, dirty: false, busy: false, generation: 0,
    };
    quoteImageEditors.set(root, state);
    return state;
}

function quoteStatus(s, message) {
    s.status.textContent = message;
}

function quoteZoom(s, value) {
    const slider = s.root.querySelector('[data-v65-image-zoom]');
    const label = s.root.querySelector('[data-v65-image-zoom-label]');
    const scroll = s.root.querySelector('.v65-custom-photo-canvas-scroll');
    if (!slider || !scroll || !s.canvas.width) return;
    const pct = Math.max(50, Math.min(200, Number(value) || 100));
    slider.value = String(pct);
    if (label) label.textContent = '% ' + pct;
    const fit = Math.min(1, Math.max(100, scroll.clientWidth - 40) / s.canvas.width);
    const wrapper = s.canvas.parentElement;
    wrapper.style.maxWidth = 'none';
    wrapper.style.width = Math.round(s.canvas.width * fit * pct / 100) + 'px';
    s.canvas.style.width = '100%';
}

function quoteSyncOverlay(s) {
    s.overlay.width = s.canvas.width;
    s.overlay.height = s.canvas.height;
    s.widthInput.value = s.canvas.width || '';
    s.over.clearRect(0, 0, s.overlay.width, s.overlay.height);
    quoteRender(s);
    s.rect = null;
    s.start = null;
    quoteZoom(s, s.root.querySelector('[data-v65-image-zoom]')?.value);
}


function quoteClone(value) {
    return JSON.parse(JSON.stringify(value));
}

function quoteScene(s, asNew = false) {
    return {
        version: 2,
        base_index: asNew ? -1 : s.baseIndex,
        ops: asNew ? [] : quoteClone(s.operations),
        objects: quoteClone(s.objects),
    };
}

function quoteSnapshot(s, step) {
    s.history = s.history.slice(0, s.cursor + 1);
    s.history.push({
        bitmap: s.canvas.toDataURL('image/png'),
        objects: quoteClone(s.objects),
        ops: quoteClone(s.operations),
    });
    if (s.history.length > 12) s.history.splice(1, s.history.length - 12);
    s.cursor = s.history.length - 1;
    if (step) s.steps.push(step);
    s.dirty = s.cursor !== 0;
    quoteRender(s);
    quoteStatus(s, s.dirty ? 'Değişiklikler kaydedilmedi.' : 'Görsel hazır.');
}

function quoteBounds(s, object) {
    if (object.type === 'text') {
        s.over.font = (object.bold ? 'bold ' : '') + object.size + 'px sans-serif';
        return { x: object.x, y: object.y - object.size,
            w: Math.max(12, s.over.measureText(object.text).width), h: object.size * 1.2 };
    }
    if (object.type === 'path') {
        const xs = object.points.map(p => p.x), ys = object.points.map(p => p.y);
        return { x: Math.min(...xs), y: Math.min(...ys),
            w: Math.max(1, Math.max(...xs) - Math.min(...xs)),
            h: Math.max(1, Math.max(...ys) - Math.min(...ys)) };
    }
    return quoteRectangle({x:object.x,y:object.y},{x:object.x+object.w,y:object.y+object.h});
}

function quoteDrawObject(ctx, object) {
    ctx.save();
    ctx.strokeStyle = object.color; ctx.fillStyle = object.color;
    ctx.lineWidth = object.width || 3;
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    const x=object.x, y=object.y, w=object.w, h=object.h;
    if (object.type === 'text') {
        ctx.font = (object.bold ? 'bold ' : '') + object.size + 'px sans-serif';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(object.text, x, y);
    } else if (object.type === 'path') {
        if (object.points.length) {
            ctx.beginPath(); ctx.moveTo(object.points[0].x,object.points[0].y);
            for (const point of object.points.slice(1)) ctx.lineTo(point.x,point.y);
            ctx.stroke();
        }
    } else if (object.type === 'arrow') {
        const endX=x+w,endY=y+h,angle=Math.atan2(h,w);
        const head=Math.max(10, (object.width || 3)*4);
        ctx.beginPath();ctx.moveTo(x,y);ctx.lineTo(endX,endY);
        ctx.moveTo(endX-head*Math.cos(angle-Math.PI/6),endY-head*Math.sin(angle-Math.PI/6));
        ctx.lineTo(endX,endY);
        ctx.lineTo(endX-head*Math.cos(angle+Math.PI/6),endY-head*Math.sin(angle+Math.PI/6));
        ctx.stroke();
    } else if (object.type === 'rectangle') {
        ctx.strokeRect(x,y,w,h);
    } else if (object.type === 'circle') {
        ctx.beginPath();
        ctx.ellipse(x+w/2,y+h/2,Math.max(1,Math.abs(w)/2),Math.max(1,Math.abs(h)/2),0,0,Math.PI*2);
        ctx.stroke();
    }
    ctx.restore();
}

function quoteRender(s) {
    if (!s.overlay.width) return;
    s.over.clearRect(0,0,s.overlay.width,s.overlay.height);
    for (const object of s.objects) quoteDrawObject(s.over, object);
    if (s.preview) {
        if (s.preview.type === 'crop') {
            quoteDrawShape(s.over, 'crop', s.preview.a, s.preview.b, '#1c69bb', s.canvas.width);
        } else quoteDrawObject(s.over,s.preview);
    }
    const object = s.objects.find(o=>o.id===s.selected);
    if (object) {
        const r=quoteBounds(s,object), scale=Math.max(1,s.canvas.width/750);
        s.over.save();
        s.over.strokeStyle='#2563eb';s.over.lineWidth=2*scale;
        s.over.setLineDash([5*scale,4*scale]);
        s.over.strokeRect(r.x-5*scale,r.y-5*scale,r.w+10*scale,r.h+10*scale);
        s.over.setLineDash([]);
        s.over.fillStyle='#fff';s.over.strokeStyle='#2563eb';
        s.over.fillRect(r.x+r.w-2*scale,r.y+r.h-2*scale,12*scale,12*scale);
        s.over.strokeRect(r.x+r.w-2*scale,r.y+r.h-2*scale,12*scale,12*scale);
        s.over.restore();
    }
    quoteRefreshProperties(s);
}

function quoteRefreshProperties(s) {
    const selected=s.objects.find(o=>o.id===s.selected);
    const root=s.root;
    const layerList=root.querySelector('[data-v65-image-layers]');
    if (layerList) {
        layerList.replaceChildren();
        for (const object of [...s.objects].reverse()) {
            const button=document.createElement('button');
            button.type='button';button.dataset.v65ImageLayer=String(object.id);
            button.className=object.id===s.selected?'is-selected':'';
            button.textContent=(object.type==='text' ? 'Metin · '+object.text.slice(0,22) :
                {arrow:'Ok',rectangle:'Dikdörtgen',circle:'Daire',path:'Çizim'}[object.type] || object.type);
            layerList.append(button);
        }
        if (!s.objects.length) {
            const empty=document.createElement('small');empty.textContent='Henüz işaretleme yok';
            layerList.append(empty);
        }
    }
    const panel=root.querySelector('[data-v65-image-properties]');
    if (panel) panel.hidden=!selected;
    const textField=root.querySelector('[data-v65-image-object-text]');
    if (textField) {textField.closest('label').hidden=selected?.type!=='text'; if(selected?.type==='text')textField.value=selected.text;}
    const font=root.querySelector('[data-v65-image-font-size]');
    if (font) {font.closest('label').hidden=selected?.type!=='text';if(selected?.type==='text')font.value=String(selected.size);}
    const stroke=root.querySelector('[data-v65-image-stroke-width]');
    if (stroke) {stroke.closest('label').hidden=!selected || selected.type==='text';if(selected&&selected.type!=='text')stroke.value=String(selected.width);}
    const color=root.querySelector('[data-v65-image-color]');
    if (color && selected)color.value=selected.color;
}

function quoteHit(s,pos) {
    const pad=Math.max(7,s.canvas.width/105);
    for (const o of [...s.objects].reverse()) {
        const b=quoteBounds(s,o);
        if(pos.x>=b.x-pad&&pos.y>=b.y-pad&&pos.x<=b.x+b.w+pad&&pos.y<=b.y+b.h+pad)return o;
    }
    return null;
}

function quoteHandleHit(s, pos) {
    const o=s.objects.find(item=>item.id===s.selected);
    if(!o)return false;
    const b=quoteBounds(s,o),pad=Math.max(10,s.canvas.width/85);
    return Math.abs(pos.x-(b.x+b.w))<pad && Math.abs(pos.y-(b.y+b.h))<pad;
}

function quoteMoveObject(o,dx,dy) {
    if(o.type==='path')for(const p of o.points){p.x+=dx;p.y+=dy;}
    else{o.x+=dx;o.y+=dy;}
}

function quoteScaleObject(s,o,dx,dy) {
    const b=quoteBounds(s,o);
    if(o.type==='text'){o.size=Math.max(10,Math.min(180,Math.round(o.size+dy)));return;}
    if(o.type==='path'){
        const sx=Math.max(.1,(b.w+dx)/b.w),sy=Math.max(.1,(b.h+dy)/b.h);
        for(const p of o.points){p.x=b.x+(p.x-b.x)*sx;p.y=b.y+(p.y-b.y)*sy;}
    }else{o.w=o.w>=0?Math.max(3,o.w+dx):Math.min(-3,o.w+dx);o.h=o.h>=0?Math.max(3,o.h+dy):Math.min(-3,o.h+dy);}
}

function quoteMapObjects(s, map, scale=1) {
    for(const o of s.objects) {
        if(o.type==='path')o.points=o.points.map(map);
        else {
            const p=map({x:o.x,y:o.y}),q=map({x:o.x+(o.w||0),y:o.y+(o.h||0)});
            o.x=p.x;o.y=p.y;
            if(o.type==='text')o.size=Math.max(10,Math.round(o.size*scale));
            else{o.w=q.x-p.x;o.h=q.y-p.y;o.width=Math.max(1,Math.round(o.width*scale));}
        }
    }
}

function quoteNewObject(s,type,pos,end) {
    const id=s.nextId++;
    const base={id,type,color:s.color.value,width:Number(s.root.querySelector('[data-v65-image-default-stroke]')?.value)||3};
    if(type==='text')return {...base,x:pos.x,y:pos.y,
        text:s.textInput.value.trim().slice(0,120)||'Metin',size:Math.max(10,Math.min(180,Number(s.root.querySelector('[data-v65-image-default-font]')?.value)||24)),bold:true};
    if(type==='path')return {...base,points:[pos]};
    if(type==='arrow')return {...base,x:pos.x,y:pos.y,w:end.x-pos.x,h:end.y-pos.y};
    const r=quoteRectangle(pos,end);
    return {...base,x:r.x,y:r.y,w:Math.max(1,r.w),h:Math.max(1,r.h)};
}


async function quoteLoadCanvas(s, url, fresh = true) {
    const token = ++s.generation;
    const img = new Image();
    img.decoding = 'async';
    img.src = url;
    await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = () => reject(new Error('Görsel yüklenemedi.'));
    });
    if (token !== s.generation || !s.root.isConnected) return;
    const ratio = Math.min(1, 3200 / img.naturalWidth, 3200 / img.naturalHeight,
        Math.sqrt(8000000 / (img.naturalWidth * img.naturalHeight)));
    s.canvas.width = Math.max(1, Math.round(img.naturalWidth * ratio));
    s.canvas.height = Math.max(1, Math.round(img.naturalHeight * ratio));
    s.ctx.drawImage(img, 0, 0, s.canvas.width, s.canvas.height);
    quoteSyncOverlay(s);
    if (fresh) quoteSnapshot(s);
    quoteStatus(s, s.dirty ? 'Değişiklikler kaydedilmedi.' : 'Görsel hazır.');
}

async function quoteSelect(s, button, original = false) {
    if (s.busy) return;
    if (s.dirty && !window.confirm('Kaydedilmemiş görsel değişikliklerini iptal etmek istiyor musunuz?')) return;
    const id = String(button.dataset.imageId || '');
    if (!(/^[1-9][0-9]*$/.test(id) || /^staged-(0|[1-9][0-9]*)$/.test(id))) return;
    s.id = id;
    s.item = button;
    s.root.closest('dialog')?.querySelectorAll('[data-v65-image-select]').forEach(
        (element) => element.classList.toggle('is-selected', element === button)
    );
    quoteStatus(s, 'Görsel açılıyor…');
    try {
        const scene=quoteDefaultScene(button,original);
        s.baseIndex=scene.base_index;
        await quoteLoadCanvas(s,original?button.dataset.imageOriginal:(button.dataset.imageBase||button.dataset.imageUrl),false);
        quoteReplay(s,scene);
        s.history=[];s.cursor=-1;s.steps=[];s.dirty=false;s.mode='select';
        s.root.querySelectorAll('[data-v65-image-tool]').forEach(b=>b.classList.toggle('is-active',b.dataset.v65ImageTool==='select'));
        quoteSnapshot(s);
    } catch {
        quoteStatus(s, 'Görsel yüklenemedi. Dosyayı ve erişim yetkisini kontrol edin.');
    }
}

function quotePosition(s, event) {
    const r = s.overlay.getBoundingClientRect();
    return {
        x: Math.round(Math.max(0, Math.min(s.canvas.width, (event.clientX - r.left) * s.canvas.width / r.width))),
        y: Math.round(Math.max(0, Math.min(s.canvas.height, (event.clientY - r.top) * s.canvas.height / r.height))),
    };
}

function quoteRectangle(a, b) {
    return { x: Math.min(a.x, b.x), y: Math.min(a.y, b.y),
        w: Math.abs(b.x - a.x), h: Math.abs(b.y - a.y) };
}

function quoteDrawShape(ctx, mode, a, b, hex, imageWidth) {
    ctx.strokeStyle = hex;
    ctx.fillStyle = hex;
    ctx.lineWidth = Math.max(2, Math.round(imageWidth / 320));
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const r = quoteRectangle(a, b);
    if (mode === 'crop') {
        ctx.setLineDash([Math.max(5, ctx.lineWidth * 3), Math.max(4, ctx.lineWidth * 2)]);
        ctx.strokeRect(r.x, r.y, r.w, r.h);
        ctx.setLineDash([]);
    } else if (mode === 'rectangle') {
        ctx.strokeRect(r.x, r.y, r.w, r.h);
    } else if (mode === 'circle') {
        ctx.beginPath();
        ctx.ellipse(r.x + r.w / 2, r.y + r.h / 2, Math.max(1, r.w / 2), Math.max(1, r.h / 2), 0, 0, Math.PI * 2);
        ctx.stroke();
    } else if (mode === 'arrow') {
        const angle = Math.atan2(b.y - a.y, b.x - a.x);
        const head = Math.max(12, imageWidth / 35);
        ctx.beginPath();
        ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
        ctx.moveTo(b.x - head * Math.cos(angle - Math.PI / 6), b.y - head * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(b.x, b.y);
        ctx.lineTo(b.x - head * Math.cos(angle + Math.PI / 6), b.y - head * Math.sin(angle + Math.PI / 6));
        ctx.stroke();
    }
}


function quoteRestore(s,index) {
    if(index<0||index>=s.history.length||s.busy)return;
    const snapshot=s.history[index];
    const token=++s.generation;
    const img=new Image();
    img.onload=()=>{
        if(token!==s.generation||!s.root.isConnected)return;
        s.cursor=index;
        s.canvas.width=img.naturalWidth;s.canvas.height=img.naturalHeight;
        s.ctx.drawImage(img,0,0);
        s.objects=quoteClone(snapshot.objects);s.operations=quoteClone(snapshot.ops);
        s.selected=null;s.preview=null;
        quoteSyncOverlay(s);
        s.dirty=index!==0;
        quoteStatus(s,s.dirty?'Değişiklikler kaydedilmedi.':'Görsel hazır.');
    };
    img.src=snapshot.bitmap;
}


function quoteTransform(s, direction, track = true) {
    const old = s.canvas;
    const turn = direction === 'rotate-left' || direction === 'rotate-right';
    const buffer = document.createElement('canvas');
    buffer.width = turn ? old.height : old.width;
    buffer.height = turn ? old.width : old.height;
    const ctx = buffer.getContext('2d');
    ctx.translate(buffer.width / 2, buffer.height / 2);
    if (direction === 'rotate-left') ctx.rotate(-Math.PI / 2);
    if (direction === 'rotate-right') ctx.rotate(Math.PI / 2);
    if (direction === 'flip-horizontal') ctx.scale(-1, 1);
    if (direction === 'flip-vertical') ctx.scale(1, -1);
    ctx.drawImage(old, -old.width / 2, -old.height / 2);
    old.width = buffer.width; old.height = buffer.height;
    s.ctx.drawImage(buffer, 0, 0);
    if(track) {
        const width=old.width,height=old.height,ow=turn?height:width,oh=turn?width:height;
        const map=p=>{
            if(direction==='rotate-left')return {x:p.y,y:ow-p.x};
            if(direction==='rotate-right')return {x:oh-p.y,y:p.x};
            if(direction==='flip-horizontal')return {x:width-p.x,y:p.y};
            return {x:p.x,y:height-p.y};
        };
        quoteMapObjects(s,map);
        s.operations.push({kind:direction});
    }
    quoteSyncOverlay(s);
    if(track)quoteSnapshot(s,direction);
}

function quoteResize(s, track = true, replayWidth = null) {
    const width = replayWidth === null ? Number(s.widthInput.value) : Number(replayWidth);
    if (!Number.isInteger(width) || width < 1 || width > 3200) {
        quoteStatus(s, 'Genişlik 1–3200 piksel arasında olmalıdır.');
        return;
    }
    const height = Math.max(1, Math.round(s.canvas.height * width / s.canvas.width));
    if (height > 3200 || height * width > 10000000) {
        quoteStatus(s, 'Görsel çok büyük; daha küçük bir genişlik girin.');
        return;
    }
    if (width === s.canvas.width) return;
    const oldWidth=s.canvas.width;
    const buffer = document.createElement('canvas');
    buffer.width = width; buffer.height = height;
    const ctx = buffer.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(s.canvas, 0, 0, width, height);
    s.canvas.width = width; s.canvas.height = height;
    s.ctx.drawImage(buffer, 0, 0);
    if(track) {
        const scale=width/oldWidth;
        quoteMapObjects(s,p=>({x:p.x*scale,y:p.y*scale}),scale);
        s.operations.push({kind:'resize',width});
    }
    quoteSyncOverlay(s);
    if(track)quoteSnapshot(s,'resize ' + width + 'px');
}


async function quoteSave(s, asNew = false) {
    if(s.busy||!s.id||!s.dirty){quoteStatus(s,'Kaydedilecek bir değişiklik yok.');return;}
    if(s.objects.length>60){quoteStatus(s,'En fazla 60 işaretleme oluşturabilirsiniz.');return;}
    const holder=s.root.closest('[wire\\:id]');
    const component=holder&&window.Livewire?.find(holder.getAttribute('wire:id'));
    if(!component){quoteStatus(s,'Teklif bağlantısı bulunamadı; sayfayı yenileyin.');return;}
    const exportCanvas=document.createElement('canvas');
    exportCanvas.width=s.canvas.width;exportCanvas.height=s.canvas.height;
    const ctx=exportCanvas.getContext('2d');
    ctx.drawImage(s.canvas,0,0);
    for(const object of s.objects)quoteDrawObject(ctx,object);
    const scene=quoteScene(s,asNew);
    const toPng=canvas=>new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
    const rendered=await toPng(exportCanvas);
    if(!rendered||rendered.size>5*1024*1024){quoteStatus(s,'Görsel 5 MB sınırını aşıyor; boyutlandırıp tekrar deneyin.');return;}
    const base=asNew?await toPng(s.canvas):null;
    if(asNew&&(!base||base.size>5*1024*1024)){quoteStatus(s,'Temel görsel 5 MB sınırını aşıyor.');return;}
    s.busy=true;quoteStatus(s,'Düzenlenen görsel kaydediliyor…');
    const upload=(property,blob)=>new Promise((resolve,reject)=>{
        const file=new File([blob],property+'.png',{type:'image/png'});
        component.upload(property,file,resolve,reject);
    });
    try{
        if(asNew)await upload('customImageBaseUpload',base);
        await upload('customImageEditUpload',rendered);
        const pending=s.id.startsWith('staged-');
        const method=pending
            ?(asNew?'savePendingCustomImageAsNew':'savePendingCustomImageEdit')
            :(asNew?'saveCustomImageAsNew':'saveCustomImageEdit');
        const args=asNew?[scene]:[pending?Number(s.id.slice(7)):Number(s.id),scene];
        await component.call(method,...args);
        // The Livewire event confirms success; a rejected validation must not mark the scene saved.
        if(s.busy)quoteStatus(s,'Kaydetme onayı alınamadı; form hatalarını kontrol edin.');
    }catch{
        quoteStatus(s,'Kaydetme başarısız. Form hatalarını kontrol edin.');
    }finally{s.busy=false;}
}



function quoteCrop(s,rect,track=true) {
    if(rect.w<3||rect.h<3)return;
    const buffer=document.createElement('canvas');
    buffer.width=rect.w;buffer.height=rect.h;
    buffer.getContext('2d').drawImage(s.canvas,rect.x,rect.y,rect.w,rect.h,0,0,rect.w,rect.h);
    s.canvas.width=rect.w;s.canvas.height=rect.h;s.ctx.drawImage(buffer,0,0);
    if(track){
        quoteMapObjects(s,p=>({x:p.x-rect.x,y:p.y-rect.y}));
        s.objects=s.objects.filter(o=>{const b=quoteBounds(s,o);return b.x+b.w>=0&&b.y+b.h>=0&&b.x<rect.w&&b.y<rect.h;});
        s.operations.push({kind:'crop',x:rect.x,y:rect.y,w:rect.w,h:rect.h});
    }
    s.preview=null;quoteSyncOverlay(s);
    if(track)quoteSnapshot(s,'crop');
}

function quoteAction(s,action) {
    if(!s.id||s.busy||!s.history.length)return;
    if(['rotate-left','rotate-right','flip-horizontal','flip-vertical'].includes(action))quoteTransform(s,action);
    else if(action==='crop-apply'){
        if(!s.rect||s.rect.w<3||s.rect.h<3){quoteStatus(s,'Önce kırpılacak alanı seçin.');return;}
        quoteCrop(s,s.rect);
    }else if(action==='resize')quoteResize(s);
    else if(action==='undo')quoteRestore(s,s.cursor-1);
    else if(action==='redo')quoteRestore(s,s.cursor+1);
    else if(action==='reset')quoteRestore(s,0);
    else if(action==='original')void quoteSelect(s,s.item,true);
    else if(action==='fit')quoteZoom(s,100);
    else if(action==='save')void quoteSave(s);
    else if(action==='save-as')void quoteSave(s,true);
    else if(['delete','duplicate','front','back'].includes(action)){
        const i=s.objects.findIndex(o=>o.id===s.selected);
        if(i<0)return;
        if(action==='delete'){s.objects.splice(i,1);s.selected=null;}
        if(action==='duplicate'){
            if(s.objects.length>=60)return;
            const duplicate=quoteClone(s.objects[i]);duplicate.id=s.nextId++;quoteMoveObject(duplicate,16,16);
            s.objects.push(duplicate);s.selected=duplicate.id;
        }
        if(action==='front'&&i<s.objects.length-1){const [item]=s.objects.splice(i,1);s.objects.push(item);}
        if(action==='back'&&i>0){const [item]=s.objects.splice(i,1);s.objects.unshift(item);}
        quoteSnapshot(s,action);
    }
}

function quoteReplay(s,scene) {
    s.operations=[];
    for(const op of scene.ops||[]){
        if(['rotate-left','rotate-right','flip-horizontal','flip-vertical'].includes(op.kind))
            quoteTransform(s,op.kind,false);
        else if(op.kind==='resize')quoteResize(s,false,op.width);
        else if(op.kind==='crop')quoteCrop(s,{x:op.x,y:op.y,w:op.w,h:op.h},false);
        s.operations.push(quoteClone(op));
    }
    s.objects=quoteClone(scene.objects||[]);
    s.nextId=Math.max(0,...s.objects.map(o=>Number(o.id)||0))+1;
    s.selected=null;s.preview=null;
    quoteRender(s);
}

function quoteDefaultScene(button,original) {
    if(original)return {version:2,base_index:-1,ops:[],objects:[]};
    try{
        const scene=JSON.parse(button.dataset.imageScene||'{}');
        if(scene.version===2&&Array.isArray(scene.ops)&&Array.isArray(scene.objects)
            &&scene.objects.length<=60&&scene.ops.length<=40)return scene;
    }catch{ /* Legacy versions were flattened without an editable scene. */ }
    return {version:2,base_index:Number(button.dataset.imageBaseIndex||-1),ops:[],objects:[]};
}



document.addEventListener('pointerdown',event=>{
    const overlay=event.target;
    if(!(overlay instanceof HTMLCanvasElement)||!overlay.matches('[data-v65-image-overlay]'))return;
    const s=quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if(!s||!s.id||s.busy)return;
    const pos=quotePosition(s,event);
    if(s.mode==='select'){
        const handle=quoteHandleHit(s,pos),hit=handle?s.objects.find(o=>o.id===s.selected):quoteHit(s,pos);
        s.selected=hit?.id??null;
        s.drag=hit?{action:handle?'resize':'move',last:pos,changed:false}:null;
        quoteRender(s);
    }else if(s.mode==='text'){
        if(s.objects.length>=60){quoteStatus(s,'En fazla 60 nesne ekleyebilirsiniz.');return;}
        const object=quoteNewObject(s,'text',pos,pos);
        s.objects.push(object);s.selected=object.id;quoteSnapshot(s,'text');
        s.mode='select';s.root.querySelectorAll('[data-v65-image-tool]').forEach(b=>b.classList.toggle('is-active',b.dataset.v65ImageTool==='select'));
    }else{
        s.start=pos;s.drag={action:'create',last:pos,changed:false};
        if(s.mode==='draw')s.preview=quoteNewObject(s,'path',pos,pos);
        else s.preview={type:'crop',a:pos,b:pos};
    }
    overlay.setPointerCapture(event.pointerId);
});

document.addEventListener('pointermove',event=>{
    const overlay=event.target;
    if(!(overlay instanceof HTMLCanvasElement)||!overlay.matches('[data-v65-image-overlay]'))return;
    const s=quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if(!s?.drag)return;
    const pos=quotePosition(s,event),drag=s.drag;
    if(drag.action==='move'||drag.action==='resize'){
        const obj=s.objects.find(o=>o.id===s.selected);
        if(obj){
            const dx=pos.x-drag.last.x,dy=pos.y-drag.last.y;
            if(dx||dy){
                if(drag.action==='move')quoteMoveObject(obj,dx,dy);
                else quoteScaleObject(s,obj,dx,dy);
                drag.changed=true;
            }
        }
        drag.last=pos;
    }else if(s.mode==='draw'){
        if(s.preview?.points?.length<400)s.preview.points.push(pos);
        drag.changed=true;
    }else{
        if(s.mode==='crop')s.preview={type:'crop',a:s.start,b:pos};
        else s.preview=quoteNewObject(s,s.mode,s.start,pos);
        drag.changed=true;
    }
    quoteRender(s);
});

document.addEventListener('pointerup',event=>{
    const overlay=event.target;
    if(!(overlay instanceof HTMLCanvasElement)||!overlay.matches('[data-v65-image-overlay]'))return;
    const s=quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if(!s?.drag)return;
    const drag=s.drag,pos=quotePosition(s,event);s.drag=null;
    if(drag.action==='create'){
        if(s.mode==='crop'){
            s.rect=quoteRectangle(s.start,pos);s.preview={type:'crop',a:s.start,b:pos};
            quoteStatus(s,'Kırpmayı Uygula ile seçili alanı onaylayın.');
        }else if(s.objects.length<60&&(s.mode==='draw'||Math.hypot(pos.x-s.start.x,pos.y-s.start.y)>4)){
            const o=s.mode==='draw'?s.preview:quoteNewObject(s,s.mode,s.start,pos);
            if(o&&!(o.type==='path'&&o.points.length<2)){s.objects.push(o);s.selected=o.id;quoteSnapshot(s,s.mode);}
            s.preview=null;
        }else s.preview=null;
    }else if(drag.changed)quoteSnapshot(s,drag.action);
    s.start=null;quoteRender(s);
});

document.addEventListener('pointercancel',event=>{
    const overlay=event.target;
    if(!(overlay instanceof HTMLCanvasElement)||!overlay.matches('[data-v65-image-overlay]'))return;
    const s=quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if(s){s.drag=null;s.start=null;s.preview=null;quoteRender(s);}
});

document.addEventListener('dblclick',event=>{
    const target=event.target;
    if(!(target instanceof HTMLCanvasElement)||!target.matches('[data-v65-image-overlay]'))return;
    const s=quoteImageEditors.get(target.closest('[data-v65-quote-image-editor]'));
    if(!s)return;
    const hit=quoteHit(s,quotePosition(s,event));
    if(hit?.type==='text'){
        s.selected=hit.id;quoteRender(s);
        const input=s.root.querySelector('[data-v65-image-object-text]');
        input?.focus();input?.select();
    }
});

document.addEventListener('keydown',event=>{
    const dialog=event.target?.closest?.('#v65-custom-quote-dialog');
    if(!dialog||event.target.closest('input,textarea,[contenteditable]'))return;
    const root=dialog.querySelector('[data-v65-quote-image-editor]');
    const s=root&&quoteImageEditors.get(root);
    if(!s?.id)return;
    if((event.key==='Delete'||event.key==='Backspace')&&s.selected){event.preventDefault();quoteAction(s,'delete');}
    else if(event.key==='Escape'){s.selected=null;s.mode='select';quoteRender(s);}
    else if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='z'){
        event.preventDefault();quoteAction(s,event.shiftKey?'redo':'undo');
    }else if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='y'){
        event.preventDefault();quoteAction(s,'redo');
    }else if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='d'){
        event.preventDefault();quoteAction(s,'duplicate');
    }
});


document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const select = target.closest('[data-v65-image-select]');
    if (select instanceof HTMLButtonElement) {
        const root = select.closest('dialog')?.querySelector('[data-v65-quote-image-editor]');
        if (root) void quoteSelect(quoteImageEditor(root), select);
        return;
    }
    const zoomStep = target.closest('[data-v65-image-zoom-step]');
    if (zoomStep instanceof HTMLButtonElement) {
        const root = zoomStep.closest('[data-v65-quote-image-editor]');
        if (root) {
            const current = Number(root.querySelector('[data-v65-image-zoom]')?.value || 100);
            quoteZoom(quoteImageEditor(root), current + Number(zoomStep.dataset.v65ImageZoomStep));
        }
        return;
    }
    const tool = target.closest('[data-v65-image-tool]');
    if (tool instanceof HTMLButtonElement) {
        const root = tool.closest('[data-v65-quote-image-editor]');
        if (!root) return;
        const s = quoteImageEditor(root);
        s.mode = tool.dataset.v65ImageTool;
        s.preview=null;s.rect=null;quoteRender(s);
        root.querySelectorAll('[data-v65-image-tool]').forEach(b => b.classList.toggle('is-active', b === tool));
        quoteStatus(s, 'Araç seçildi: ' + tool.textContent.trim());
        return;
    }
    const layer=target.closest('[data-v65-image-layer]');
    if(layer instanceof HTMLButtonElement){
        const root=layer.closest('[data-v65-quote-image-editor]');
        const s=root&&quoteImageEditors.get(root);
        if(s){s.selected=Number(layer.dataset.v65ImageLayer);s.mode='select';quoteRender(s);}
        return;
    }
    const action = target.closest('[data-v65-image-action]');
    if (action instanceof HTMLButtonElement) {
        const root = action.closest('[data-v65-quote-image-editor]');
        if (root) quoteAction(quoteImageEditor(root), action.dataset.v65ImageAction);
    }
});

document.addEventListener('v65-custom-quote-open', () => {
    // Livewire renders the selected row before dispatching this event.
    queueMicrotask(() => {
        const dialog = document.getElementById('v65-custom-quote-dialog');
        const root = dialog?.querySelector('[data-v65-quote-image-editor]');
        const first = dialog?.querySelector('[data-v65-image-select]');
        if (root && first) {
            const s = quoteImageEditor(root);
            s.dirty = false; // A new popup visit explicitly starts a new edit session.
            void quoteSelect(s, first);
        }
    });
});


// Server-side authorization and persistence, not the upload callback, confirms success.
document.addEventListener('v65-quote-image-edited', (event) => {
    const dialog = document.getElementById('v65-custom-quote-dialog');
    const root = dialog?.querySelector('[data-v65-quote-image-editor]');
    const s = root && quoteImageEditors.get(root);
    const id = String(event.detail?.attachmentId ?? '');
    if (!s || id !== s.id) return;

    s.busy = false;
    s.dirty = false;
    s.steps = [];
    s.history=[];s.cursor=-1;quoteSnapshot(s);
    const item = dialog.querySelector('[data-v65-image-select][data-image-id="' + id + '"]');
    if (item instanceof HTMLButtonElement) {
        s.item = item;
        item.dataset.imageScene = JSON.stringify(quoteScene(s));
        item.dataset.imageBaseIndex = String(s.baseIndex);
        // Livewire temporary URLs are signed: never discard their query string.
        const current = id.startsWith('staged-')
            ? item.dataset.imageUrl
            : item.dataset.imageUrl.split('?')[0] + '?refresh=' + Date.now();
        item.dataset.imageUrl = current;
        const preview = item.closest('.v65-custom-quote-image')?.querySelector('img');
        if (preview) preview.src = current;
    }
    quoteStatus(s, 'Yeni görsel sürümü kaydedildi. Orijinal ve geçmiş revizyonlar korundu.');
});


document.addEventListener('v65-quote-image-added', () => {
    const dialog = document.getElementById('v65-custom-quote-dialog');
    const root = dialog?.querySelector('[data-v65-quote-image-editor]');
    const s = root && quoteImageEditors.get(root);
    if (!s) return;
    s.busy = false;
    s.dirty = false;
    s.steps = [];
    s.history=[];s.cursor=-1;quoteSnapshot(s);
    quoteStatus(s, 'Düzenlenmiş resim yeni bir görsel olarak eklendi. Diğer görseller korundu.');
});


document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-v65-image-zoom]')) return;
    const root = input.closest('[data-v65-quote-image-editor]');
    if (root) quoteZoom(quoteImageEditor(root), input.value);
});


// Images attached to unsaved quote rows are visible/editable immediately.
document.addEventListener('v65-quote-image-staged', () => {
    queueMicrotask(() => {
        const dialog = document.getElementById('v65-custom-quote-dialog');
        const root = dialog?.querySelector('[data-v65-quote-image-editor]');
        if (!root) return;
        const s = quoteImageEditor(root);
        if (s.dirty || s.busy) return;
        const matches = Array.from(dialog.querySelectorAll('[data-v65-image-select]'));
        if (!matches.some(button => button.dataset.imageId === s.id)) {
            const staged = matches.find(button => button.dataset.imageId?.startsWith('staged-'));
            if (staged) void quoteSelect(s, staged);
        }
    });
});


// Property editing occurs only on the selected object; no generated HTML is evaluated.
document.addEventListener('change',event=>{
    const element=event.target;
    if(!(element instanceof HTMLInputElement))return;
    const root=element.closest('[data-v65-quote-image-editor]');
    if(!root)return;
    const s=quoteImageEditors.get(root);
    const object=s?.objects.find(o=>o.id===s.selected);
    if(!object)return;
    if(element.matches('[data-v65-image-object-text]')&&object.type==='text')
        object.text=element.value.slice(0,120)||'Metin';
    else if(element.matches('[data-v65-image-font-size]')&&object.type==='text')
        object.size=Math.max(10,Math.min(180,Number(element.value)||24));
    else if(element.matches('[data-v65-image-stroke-width]')&&object.type!=='text')
        object.width=Math.max(1,Math.min(30,Number(element.value)||3));
    else if(element.matches('[data-v65-image-color]'))
        object.color=element.value;
    else return;
    quoteSnapshot(s,'property');
});

document.addEventListener('input',event=>{
    const target=event.target;
    if(!(target instanceof HTMLInputElement)||!target.matches('[data-v65-image-color]'))return;
    const root=target.closest('[data-v65-quote-image-editor]'),s=root&&quoteImageEditors.get(root);
    const object=s?.objects.find(o=>o.id===s.selected);
    if(object){object.color=target.value;quoteRender(s);}
});
