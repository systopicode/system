function cmsQuery(url, data) {
	var params = new URLSearchParams();
	Object.keys(data).forEach(function (key) {
		params.set(key, data[key]);
	});
	return fetch(url + '?' + params.toString(), {credentials: 'same-origin'});
}
function url2mediaParams(url) {
	var params = url.match(/\/var_?[a-z]*\/formats\/([^\/]*)\/([0-9]*)\/([0-9]*)\//);
    if (!params) return null;
	return {
		media_id: parseInt(' ' + params[2] + params[3]),
		media_crop_format: params[1]
	}
}
function runImageEditor(params) {
    const openedAt = location.href;
    const media = url2mediaParams(params.src);
    if (!media) return;
    const existing = document.querySelector('.imageEditor');
    if (existing && existing !== params.replaceEditor) {
        if (!params.inline || !existing.classList.contains('inlineImageCrop') || !existing.requestReplace?.()) return;
    }
    const $ = window.jQuery;
    return cmsQuery(settings.siteRoot + 'admin/pages/getImageEditor', {
        media_id: media.media_id, image_format: media.media_crop_format,
        width: params.display_width, height: params.display_height, ajax: 1
    }).then(response => response.json()).then(response => {
        if(location.href!==openedAt || (params.replaceEditor && !params.replaceEditor.isConnected))return;
        if(!response.html){if(params.replaceEditor)throw new Error('Image format could not be loaded');return;}
        const editor = $(response.html)[0];
        const crop = editor.querySelector('.cropFrame');
        const cropImage = editor.querySelector('.cropimage');
        const outer = editor.querySelector('.dragimage');
        const controls = editor.querySelector('.cropHandles');
        if (!crop || !cropImage || !outer || !controls) return;
        outer.querySelector('img').style.opacity = '.35';
        const scrim = document.createElement('div'); scrim.className = 'imageEditorScrim';
        if(params.inline){editor.classList.add('inlineImageCrop');scrim.classList.add('inlineImageCropScrim');}
        params.onReplace?.();
        document.body.append(scrim, editor);
        const previewFrame = params.domObj[0].closest('.frameView');
        const previewFrameVisibility = previewFrame?.style.visibility;
        if (previewFrame) previewFrame.style.visibility = 'hidden';
        params.domObj.css('visibility','hidden');
        let frame = {x:0,y:0,w:parseFloat(crop.style.width),h:parseFloat(crop.style.height)};
        let image = {x:parseFloat(cropImage.style.marginLeft)||0,y:parseFloat(cropImage.style.marginTop)||0,
            w:parseFloat(cropImage.style.width),h:parseFloat(cropImage.style.height)};
        let origin = {x:params.offset_left,y:params.offset_top};
        let ratio = Number(editor.dataset.cropRatio)||0;
        const fixedRatio = ratio;
        const unified=!!params.unified;
        let cropping=!unified, toggleCropping, cropStart;
        const savedTune=JSON.parse(editor.dataset.tune || '{}');
        let tune={b:savedTune.b??100,c:savedTune.c??100,s:savedTune.s??100,r:savedTune.r??0,f:savedTune.f??0};
        const initialTune={...tune};
        const originalButton = editor.querySelector('[data-crop-original]');
        originalButton.hidden = !!fixedRatio;
        const aspectGroup=document.createElement('div');aspectGroup.className='editorAspect';originalButton.before(aspectGroup);aspectGroup.append(originalButton);
        const lockButton=document.createElement('button');lockButton.type='button';lockButton.className='button';lockButton.dataset.cropLock='';lockButton.hidden=!!fixedRatio;
        aspectGroup.append(lockButton);
        lockButton.addEventListener('click',()=>{if(saving || fixedRatio)return;ratio=ratio?0:frame.w/frame.h;render();});
        const initial = {frame:{...frame},image:{...image},ratio};
        let edgeFrame=0;
        let gesture = null, pinch = null, saving = false, timer, preview, ready, failed;
        const selection = () => [frame.w/image.w, frame.h/image.h,
            (frame.x+frame.w/2-image.x)/image.w, (frame.y+frame.h/2-image.y)/image.h];
        const initialSelection = selection();
        const tuneChanged = () => JSON.stringify(tune)!==JSON.stringify(initialTune);
        const changed = () => selection().some((value,i)=>Math.abs(value-initialSelection[i])>1e-6) || (unified && tuneChanged());
        const px = value => value+'px';
        const area = () => {
            const bar = editor.querySelector('.cropBar');
            if(params.inline){const r=params.domObj[0].getBoundingClientRect();return {left:r.left+scrollX,top:r.top+scrollY,right:r.right+scrollX,bottom:r.bottom+scrollY};}
            const gap=Math.max(48,Math.min(96,innerWidth*.06));
            const a = {left:gap+scrollX,top:bar.offsetTop+bar.offsetHeight+gap+scrollY,
                right:innerWidth-gap+scrollX,bottom:innerHeight-gap-(unified?48:0)+scrollY};
            // Large mouse-operated displays need not enlarge the crop to fill the screen.
            if (matchMedia('(min-width: 1400px) and (hover: hover) and (pointer: fine)').matches) {
                const dx=Math.max(0,(a.right-a.left-1100)/2),dy=Math.max(0,(a.bottom-a.top-760)/2);
                a.left+=dx;a.right-=dx;a.top+=dy;a.bottom-=dy;
            }
            return a;
        };
        function render() {
            lockButton.title=ratio?'Unlock aspect ratio':'Lock aspect ratio';lockButton.setAttribute('aria-label',lockButton.title);lockButton.setAttribute('aria-pressed',String(!!ratio));lockButton.classList.toggle('selected',!!ratio);
            lockButton.innerHTML=`<i class="cmsIcon cmsIcon-${ratio?'lock':'lock-open'}" aria-hidden="true"></i>`;
            Object.assign(editor.style,{left:px(origin.x),top:px(origin.y),width:px(frame.w),height:px(frame.h)});
            Object.assign(crop.style,{left:px(frame.x),top:px(frame.y),width:px(frame.w),height:px(frame.h)});
            Object.assign(controls.style,{left:px(frame.x),top:px(frame.y),width:px(frame.w),height:px(frame.h)});
            Object.assign(outer.style,{marginLeft:px(image.x),marginTop:px(image.y),width:px(image.w),height:px(image.h)});
            Object.assign(cropImage.style,{marginLeft:px(image.x-frame.x),marginTop:px(image.y-frame.y),width:px(image.w),height:px(image.h)});
            if(unified){
                const transform=`rotate(${tune.r}deg) scaleX(${tune.f?-1:1})`;
                const filter=`brightness(${tune.b}%) contrast(${tune.c}%) saturate(${tune.s}%)`;
                cropImage.style.transformOrigin=`${frame.w/2-image.x+frame.x}px ${frame.h/2-image.y+frame.y}px`;
                outer.style.transformOrigin=`${frame.x+frame.w/2-image.x}px ${frame.y+frame.h/2-image.y}px`;
                cropImage.style.transform=outer.style.transform=transform;
                cropImage.style.filter=outer.style.filter=filter;
                editor.classList.toggle('is-cropping',cropping);
                const slider=editor.adjustSlider;if(slider && slider.dataset.tuneKey){slider.querySelector('input').value=tune[slider.dataset.tuneKey];slider.querySelector('output').textContent=tune[slider.dataset.tuneKey]-100;}
                editor.querySelectorAll('[data-editor-apply]').forEach(b=>b.disabled=saving || !tuneChanged());
            }
            editor.querySelector('[data-crop-reset]').disabled = saving || !changed();
            editor.querySelector('.cropBar .submit').disabled = saving || !changed();
            if(params.inline){
                const content=params.domObj[0].closest('.cmsContent')?.getBoundingClientRect();
                const sidebar=document.querySelector('aside.cms')?.getBoundingClientRect();
                const bounds={top:Math.max(0,content?.top||0),left:Math.max(0,content?.left||0),
                    right:Math.min(innerWidth,content?.right||innerWidth,sidebar?.width?sidebar.left:innerWidth),
                    bottom:Math.min(innerHeight-32,content?.bottom||innerHeight)};
                [crop,controls].forEach(el=>{const r=el.getBoundingClientRect();
                    el.style.clipPath=`inset(${Math.max(-12,bounds.top-r.top)}px ${Math.max(-12,r.right-bounds.right)}px ${Math.max(-12,r.bottom-bounds.bottom)}px ${Math.max(-12,bounds.left-r.left)}px)`;
                });
            }
            const status=editor.querySelector('.editorStatus');if(status){status.hidden=!changed()&&!saving;status.innerHTML=saving?'<i class="spin"></i> Saving…':changed()?'<i class="cmsIcon cmsIcon-circle-exclamation"></i> Unsaved changes':'';}
        }
        function fit(initial=false) {
            if (saving) return;
            wheelImage=null;
            const before = [crop,outer,controls].map(el=>el.getBoundingClientRect());
            const a=area(), width=Math.max(48,a.right-a.left), height=Math.max(48,a.bottom-a.top);
            const scale=Math.min(width/frame.w,height/frame.h,initial?1:Infinity);
            image={x:(image.x-frame.x)*scale,y:(image.y-frame.y)*scale,w:image.w*scale,h:image.h*scale};
            frame={x:0,y:0,w:frame.w*scale,h:frame.h*scale};
            origin={x:a.left+(width-frame.w)/2,y:a.top+(height-frame.h)/2};
            render();
            if (!initial && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
                [crop,outer,controls].forEach((el,i)=>{
                    const after=el.getBoundingClientRect(),old=before[i];
                    if (!after.width || !after.height || !old.width) return;
                    el.animate([{transformOrigin:'0 0',transform:`translate(${old.x-after.x}px,${old.y-after.y}px) scale(${old.width/after.width},${old.height/after.height})`},
                        {transformOrigin:'0 0',transform:'none'}],{duration:180,easing:'ease-out'});
                });
            }
        }
        function guard(event){
            if (!saving && !changed()) return;
            event.preventDefault();event.returnValue='';
        }
        function close() {
            clearTimeout(timer);cancelAnimationFrame(edgeFrame);
            if(typeof closeSplitMenus==='function')closeSplitMenus();
            window.removeEventListener('popstate',close,true);
            window.removeEventListener('beforeunload',guard);
            window.removeEventListener('resize',resize);
            sourceObserver?.disconnect();
            document.removeEventListener('keydown',keys,true);
            if(params.inline){document.removeEventListener('scroll',followPage,true);document.removeEventListener('cms:rendered',checkPage);}
            document.removeEventListener('pointermove',move,true);
            document.removeEventListener('pointerup',up,true);
            document.removeEventListener('pointercancel',cancelPointer,true);
            if(preview){preview.removeEventListener('load',ready);preview.removeEventListener('error',failed);}
            editor.remove();scrim.remove();params.domObj.css('visibility','visible');
            if (previewFrame) previewFrame.style.visibility = previewFrameVisibility;
            params.domObj[0].dispatchEvent(new Event('cropend'));
        }
        function error() {
            close();
            const box=document.createElement('div');box.className='cms message';box.dataset.systemMessages='';box.setAttribute('role','alert');
            box.innerHTML='<div class="error"><p class="message">The crop could not be loaded or saved. Please try again.</p></div>';
            document.body.append(box);
        }
        function apply(all=false) {
            if(saving || !changed() || (all && !tuneChanged()))return;
            if(typeof closeSplitMenus==='function')closeSplitMenus();
            saving=true;gesture=pinch=null;
            editor.classList.add('is-saving');editor.setAttribute('aria-busy','true');
            editor.querySelectorAll('button').forEach(button=>button.disabled=true);
            crop.insertAdjacentHTML('beforeend','<div class="imageEditorSaving" role="status" aria-label="Saving crop"><i class="cmsIcon cmsIcon-spinner cmsIcon-spin" aria-hidden="true"></i></div>');
            timer=setTimeout(error,30000);
            preview=params.is_backgroundimage?new Image():params.domObj[0];
            ready=()=>{
                if(params.is_backgroundimage)params.domObj.css('background-image','url('+preview.src+')');
                if(params.inline)document.querySelectorAll('.cmsContent img').forEach(img=>{
                    if(img!==preview && (img.currentSrc || img.src).split('?')[0]===params.src.split('?')[0])img.src=preview.src;
                });
                close();
            };
            failed=error;
            preview.addEventListener('load',ready,{once:true});preview.addEventListener('error',failed,{once:true});
            cmsQuery(unified?params.saveUrl+'saveImageEdit':settings.siteRoot+'admin/pages/updateMediaCrop',{
                media_id:media.media_id,image_format:media.media_crop_format,format:media.media_crop_format,target_id:params.targetId || 0,
                width:frame.w/image.w,height:frame.h/image.h,
                left:(frame.x+frame.w/2-image.x)/image.w,top:(frame.y+frame.h/2-image.y)/image.h,ajax:1,
                ...(unified?{brightness:tune.b,contrast:tune.c,saturation:tune.s,rotate:tune.r,flip:tune.f,apply_all:all?1:0}:{})
            }).then(response=>{
                if(!editor.isConnected)return;
                if(response.ok===false || (unified && response.headers.get('X-Image-Edit')!=='saved'))throw new Error('Crop save failed');
                preview.src=params.src.split('?')[0]+'?rebuild='+Date.now();
            }).catch(()=>{if(editor.isConnected)error();});
        }
        function resized(start,edge,dx,dy) {
            const sx=edge.includes('w')?-1:edge.includes('e')?1:0,sy=edge.includes('n')?-1:edge.includes('s')?1:0;
            let w=Math.max(48,start.w+dx*sx),h=Math.max(48,start.h+dy*sy);
            if(ratio){
                const dw=sx && sy ? (dx*sx+dy*sy/ratio)/(1+1/(ratio*ratio)) : sx ? dx*sx : dy*sy*ratio;
                w=Math.max(48,48*ratio,start.w+dw);h=w/ratio;
            }
            const anchorX=start.x+(sx<0?start.w:sx>0?0:start.w/2);
            const anchorY=start.y+(sy<0?start.h:sy>0?0:start.h/2);
            // Resize freely in either direction; fit the view after release instead of blocking at its edge.
            return {x:anchorX-(sx<0?w:sx>0?0:w/2),y:anchorY-(sy<0?h:sy>0?0:h/2),w,h};
        }
        editor.addEventListener('pointerdown',event=>{
            if(saving || event.button!==0 || event.target.closest('.cropBar,.aspectRatioSelector'))return;
            event.preventDefault();
            const handle=event.target.closest('[data-crop-handle]');
            if(unified && !cropping && !handle && typeof sys.longPress==='function'){
                sys.longPress(event,editor,()=>{
                    if(saving || cropping || !editor.isConnected)return;
                    gesture=null;pinch=null;editor.classList.remove('is-interacting','longPressed','pressing');
                    toggleCropping?.();
                    if(editor.hasPointerCapture?.(event.pointerId))editor.releasePointerCapture(event.pointerId);
                });
            }
            if(!handle && cropping && !event.target.closest('img'))return;
            if(unified && !cropping){
                const a=area();
                if(frame.w<=a.right-a.left+1 && frame.h<=a.bottom-a.top+1)return;
            }
            if(unified && !cropping && handle)return;
            if(event.isTrusted)(handle || editor).setPointerCapture?.(event.pointerId);
            editor.classList.add('is-interacting');wheelImage=null;
            gesture={edgeTick:0,edgeSince:0,id:event.pointerId,x:event.pageX,y:event.pageY,origin:{...origin},edge:handle?.dataset.cropHandle,frame:{...frame},image:{...image}};
        });
        // A small magnetic plateau; raw pointer/zoom positions continue past it.
        const magnetic = distance => Math.abs(distance)<=8 ? 0 : Math.abs(distance)<22 ? Math.sign(distance)*(Math.abs(distance)-8)*22/14 : distance;
        let wheelImage=null,wheelAt=0;
        function snapImage(candidate,zoom=false,anchor={x:frame.x+frame.w/2,y:frame.y+frame.h/2}){
            const angle=(unified?tune.r:0)*Math.PI/180,cos=Math.round(Math.cos(angle)),sin=Math.round(Math.sin(angle));
            const flip=unified&&tune.f?-1:1;
            const size=i=>({w:Math.abs(cos)*i.w+Math.abs(sin)*i.h,h:Math.abs(sin)*i.w+Math.abs(cos)*i.h});
            let result={...candidate},s=size(result);
            if(zoom){
                const matches=[{d:s.w-frame.w,target:frame.w,value:s.w},{d:s.h-frame.h,target:frame.h,value:s.h}].sort((a,b)=>Math.abs(a.d)-Math.abs(b.d));
                const m=matches[0],scale=(m.target+magnetic(m.d))/m.value;
                result={x:anchor.x-(anchor.x-result.x)*scale,y:anchor.y-(anchor.y-result.y)*scale,w:result.w*scale,h:result.h*scale};s=size(result);
            }
            const cx=frame.x+frame.w/2,cy=frame.y+frame.h/2;
            const ix=result.x+result.w/2-cx,iy=result.y+result.h/2-cy;
            const bx=cx+cos*flip*ix-sin*iy-s.w/2,by=cy+sin*flip*ix+cos*iy-s.h/2;
            const nearest=values=>values.sort((a,b)=>Math.abs(a)-Math.abs(b))[0];
            const gridOn=!editor.querySelector('.cropGrid').hidden;
            const divisions=gridOn?[0,1/3,2/3,1]:[0,1],features=gridOn?[0,1/3,.5,2/3,1]:[0,1];
            const dx=nearest(divisions.flatMap(t=>features.map(u=>bx+s.w*u-frame.x-frame.w*t)));
            const dy=nearest(divisions.flatMap(t=>features.map(u=>by+s.h*u-frame.y-frame.h*t)));
            const sx=magnetic(dx)-dx,sy=magnetic(dy)-dy;
            result.x+=(cos*sx+sin*sy)*flip;result.y+=-sin*sx+cos*sy;
            return result;
        }
        function snapFrame(start,edge,dx,dy){
            const raw=resized(start,edge,dx,dy),angle=(unified?tune.r:0)*Math.PI/180;
            const cos=Math.round(Math.cos(angle)),sin=Math.round(Math.sin(angle)),flip=unified&&tune.f?-1:1;
            const cx=raw.x+raw.w/2,cy=raw.y+raw.h/2;
            const ix=image.x+image.w/2-cx,iy=image.y+image.h/2-cy;
            const w=Math.abs(cos)*image.w+Math.abs(sin)*image.h,h=Math.abs(sin)*image.w+Math.abs(cos)*image.h;
            const left=cx+cos*flip*ix-sin*iy-w/2,top=cy+sin*flip*ix+cos*iy-h/2;
            const targets=(v,size)=>editor.querySelector('.cropGrid').hidden?[v,v+size]:[v,v+size/3,v+size*2/3,v+size];
            const snap=(value,list)=>{const d=list.map(t=>value-t).sort((a,b)=>Math.abs(a)-Math.abs(b))[0];return magnetic(d)-d;};
            if(/[we]/.test(edge))dx+=snap(raw.x+(edge.includes('e')?raw.w:0),targets(left,w));
            if(/[ns]/.test(edge))dy+=snap(raw.y+(edge.includes('s')?raw.h:0),targets(top,h));
            return resized(start,edge,dx,dy);
        }
        function move(event){
            if(!gesture || pinch || saving || event.pointerId!==gesture.id)return;
            event.preventDefault();
            const dx=event.pageX-gesture.x,dy=event.pageY-gesture.y;
            if(unified && !cropping){origin={x:gesture.origin.x+dx,y:gesture.origin.y+dy};}
            else if(gesture.edge){
                const candidate=snapFrame(gesture.frame,gesture.edge,dx,dy);
                const outward=candidate.w>frame.w || candidate.h>frame.h;
                const atEdge=outward && (event.clientX<40 || event.clientX>innerWidth-40 || event.clientY<40 || event.clientY>innerHeight-40);
                if(atEdge){
                    gesture.edgeSince ||= performance.now();
                    gesture.x=event.pageX;gesture.y=event.pageY;gesture.frame={...frame};gesture.image={...image};
                    if(!edgeFrame)edgeFrame=requestAnimationFrame(extendAtEdge);
                }else{
                    gesture.edgeSince=gesture.edgeTick=0;cancelAnimationFrame(edgeFrame);edgeFrame=0;frame=candidate;
                }
            }
            else {
                const angle=unified?-tune.r*Math.PI/180:0;
                const x=(dx*Math.cos(angle)-dy*Math.sin(angle))*(unified&&tune.f?-1:1),y=dx*Math.sin(angle)+dy*Math.cos(angle);
                image=snapImage({...gesture.image,x:gesture.image.x+x,y:gesture.image.y+y});
            }
            render();
        }
        function extendAtEdge(now){
            edgeFrame=0;
            if(!gesture?.edgeSince || saving || pinch)return;
            // Hold the grip at the viewport edge; extend the crop by scaling only its source.
            if(now-gesture.edgeSince>2500 || Math.max(frame.w/image.w/Math.max(2,initialSelection[0]*4),frame.h/image.h/Math.max(2,initialSelection[1]*4))>=1)return;
            const elapsed=Math.min(40,gesture.edgeTick?now-gesture.edgeTick:16.67);gesture.edgeTick=now;
            const scale=Math.exp(-elapsed*.0005),edge=gesture.edge;
            const x=frame.x+(edge.includes('w')?frame.w:edge.includes('e')?0:frame.w/2);
            const y=frame.y+(edge.includes('n')?frame.h:edge.includes('s')?0:frame.h/2);
            image={x:x-(x-image.x)*scale,y:y-(y-image.y)*scale,w:image.w*scale,h:image.h*scale};
            gesture.image={...image};render();edgeFrame=requestAnimationFrame(extendAtEdge);
        }
        function up(event){
            if(!gesture || event.pointerId!==gesture.id)return;
            cancelAnimationFrame(edgeFrame);edgeFrame=0;
            const resizeFrame=gesture.edge;gesture=null;
            if(!pinch)editor.classList.remove('is-interacting');
            if(resizeFrame)fit();
        }
        function cancelPointer(){cancelAnimationFrame(edgeFrame);edgeFrame=0;gesture=null;pinch=null;editor.classList.remove('is-interacting');}
        editor.addEventListener('touchstart',event=>{
            if(saving || event.target.closest('.cropBar,.aspectRatioSelector'))return;
            event.preventDefault();
            if(event.touches.length!==2)return;
            gesture=null;editor.classList.add('is-interacting');
            const [a,b]=event.touches;
            pinch={frame:{...frame},origin:{...origin},distance:Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY),x:(a.pageX+b.pageX)/2-origin.x,y:(a.pageY+b.pageY)/2-origin.y,image:{...image}};
        },{passive:false});
        editor.addEventListener('touchmove',event=>{
            if(!pinch || event.touches.length!==2)return;
            event.preventDefault();const [a,b]=event.touches;
            const scale=Math.max(.1,Math.min(20,Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY)/Math.max(1,pinch.distance)));
            if(unified && !cropping){
                const x=(a.pageX+b.pageX)/2,y=(a.pageY+b.pageY)/2;
                origin={x:x-pinch.x*scale,y:y-pinch.y*scale};frame={x:pinch.frame.x*scale,y:pinch.frame.y*scale,w:pinch.frame.w*scale,h:pinch.frame.h*scale};
                image={x:pinch.image.x*scale,y:pinch.image.y*scale,w:pinch.image.w*scale,h:pinch.image.h*scale};render();return;
            }
            image=snapImage({x:(a.pageX+b.pageX)/2-origin.x-(pinch.x-pinch.image.x)*scale,
                y:(a.pageY+b.pageY)/2-origin.y-(pinch.y-pinch.image.y)*scale,w:pinch.image.w*scale,h:pinch.image.h*scale},true,{x:pinch.x,y:pinch.y});render();
        },{passive:false});
        editor.addEventListener('touchend',()=>{pinch=null;if(!gesture)editor.classList.remove('is-interacting');});
        editor.addEventListener('touchcancel',cancelPointer);
        editor.addEventListener('wheel',event=>{
            if(saving || event.target.closest('.cropBar,.aspectRatioSelector'))return;
            event.preventDefault();
            const delta=event.deltaY*(event.deltaMode===1?16:event.deltaMode===2?innerHeight:1);
            const scale=Math.exp(-Math.max(-100,Math.min(100,delta))*.002);
            const x=event.pageX-origin.x,y=event.pageY-origin.y;
            if(unified && !cropping){
                origin={x:event.pageX-x*scale,y:event.pageY-y*scale};
                frame={x:frame.x*scale,y:frame.y*scale,w:frame.w*scale,h:frame.h*scale};
                image={x:image.x*scale,y:image.y*scale,w:image.w*scale,h:image.h*scale};
            }else {
                const now=performance.now(),base=wheelImage&&now-wheelAt<250?wheelImage:image;
                wheelAt=now;wheelImage={x:x-(x-base.x)*scale,y:y-(y-base.y)*scale,w:base.w*scale,h:base.h*scale};
                image=snapImage(wheelImage,true,{x,y});
            }
            render();
        },{passive:false});
        editor.querySelector('[data-crop-grid]').addEventListener('click',event=>{
            if(saving)return;
            const button=event.currentTarget,grid=editor.querySelector('.cropGrid');
            grid.hidden=!grid.hidden;
            button.setAttribute('aria-pressed',String(!grid.hidden));
            button.classList.toggle('selected',!grid.hidden);
        });
        editor.querySelector('[data-crop-reset]').addEventListener('click',()=>{
            if(saving)return;
            gesture=pinch=null;
            frame={...initial.frame};image={...initial.image};ratio=initial.ratio;tune={...initialTune};
            fit(true);
        });
        editor.querySelector('[data-crop-original]').addEventListener('click',()=>{
            if(saving || fixedRatio)return;const originalRatio=Number(editor.dataset.originalRatio)||1;frame.h=frame.w/originalRatio;if(ratio)ratio=originalRatio;fit();
        });
        function requestClose(){if(saving || (changed() && !confirm(unified?'Discard image changes?':'Discard crop changes?')))return false;close();return true;}
        editor.requestClose = requestClose;
        editor.requestReplace = () => {
            if (saving || !confirm('Another crop is open. Discard it and start the new crop?')) return false;
            close(); return true;
        };
        editor.querySelector('[data-crop_cancel]').addEventListener('click',requestClose);
        editor.querySelector('.cropBar .submit').addEventListener('click',()=>apply());
        editor.querySelectorAll('[data-aspectratio]').forEach(button=>button.addEventListener('click',event=>{
            event.preventDefault();if(saving || fixedRatio)return;ratio=Number(button.dataset.aspectratio)||0;
            if(ratio){frame.h=frame.w/ratio;fit();}
        }));
        if(unified){
            editor.classList.add('unifiedImageEditor');
            const bar=editor.querySelector('.cropBar'),hint=bar.querySelector('span');
            const reset=bar.querySelector('[data-crop-reset]');reset.title='Discard unsaved edits';reset.setAttribute('aria-label','Discard unsaved edits');reset.querySelector('.cmsIcon').className='cmsIcon cmsIcon-eraser';
            hint.classList.add('imageEditorHint');editor.append(hint);
            hint.innerHTML='<span>Drag to move · Pinch or scroll to zoom</span><span class="editorStatus" role="status"></span>';
            const control=(label,icon,action)=>{
                const button=document.createElement('button');button.type='button';button.className='button';button.title=label;button.setAttribute('aria-label',label);
                button.innerHTML=`<i class="cmsIcon cmsIcon-${icon}" aria-hidden="true"></i>`;
                button.addEventListener('click',action);bar.prepend(button);return button;
            };
            const slider=document.createElement('div');slider.className='splitApplyList imageEditorMenu unifiedAdjustSlider';slider.hidden=true;
            slider.innerHTML='<output>0</output><input type="range" min="0" max="200" value="100">';editor.adjustSlider=slider;
            for(const [key,label,icon] of [['s','Saturation','droplet-half-2'],['c','Contrast','contrast'],['b','Brightness','sun']]){
                const b=control(label,icon,()=>{
                    const home=b.closest('.splitApply');
                    if(slider.splitHome!==home || !slider.classList.contains('is-open')){
                        closeSplitMenus();home.append(slider);home.splitList=slider;
                    }
                    slider.dataset.tuneKey=key;slider.querySelector('input').value=tune[key];
                    slider.querySelector('input').setAttribute('aria-label',label+' value');slider.querySelector('output').textContent=tune[key]-100;
                });
                const group=document.createElement('div');group.className='splitApply editorAdjustment';b.before(group);group.append(b);
                b.classList.add('splitApplyMenu');b.dataset.tuneKey=key;b.dataset.menuSelected='';b.setAttribute('aria-pressed','false');b.setAttribute('aria-expanded','false');
            }
            slider.querySelector('input').addEventListener('input',event=>{tune[slider.dataset.tuneKey]=Number(event.target.value);render();});
            const resetSaved=control('Reset saved adjustments and crop','arrow-rotate-left',()=>{tune={b:100,c:100,s:100,r:0,f:0};const aspect=Number(editor.dataset.originalRatio)||1;
                const center={x:frame.x+frame.w/2,y:frame.y+frame.h/2};
                const h=Math.min(frame.h,frame.w/(fixedRatio||aspect)),w=h*(fixedRatio||aspect);
                frame={x:center.x-w/2,y:center.y-h/2,w,h};ratio=fixedRatio;
                const imageH=Math.max(h,w/aspect),imageW=imageH*aspect;
                image={x:center.x-imageW/2,y:center.y-imageH/2,w:imageW,h:imageH};
                render();fit(true);});resetSaved.dataset.editorUtility='';
            control('Flip horizontally','reflect-horizontal',()=>{tune.f=tune.f?0:1;render();});
            control('Rotate right','rotate-right',()=>{tune.r=(tune.r+90)%360;render();});
            control('Rotate left','rotate-left',()=>{tune.r=(tune.r+270)%360;render();});
            toggleCropping=()=>{
                if(cropping && cropStart && JSON.stringify(selection())!==JSON.stringify(cropStart.selection) && !confirm('Keep this crop?')) { frame={...cropStart.frame};image={...cropStart.image};ratio=cropStart.ratio; }
                if(!cropping)cropStart={frame:{...frame},image:{...image},ratio,selection:selection()};
                cropping=!cropping;cropTool.classList.toggle('selected',cropping);cropTool.setAttribute('aria-pressed',String(cropping));
                hint.textContent=cropping?'Move image · Pinch or scroll to zoom':'Drag to move · Pinch or scroll to zoom';render();fit();
            };
            const cropTool=control('Crop image','crop-simple',toggleCropping);cropTool.setAttribute('aria-pressed','false');
            const formatSplit=document.createElement('div');formatSplit.className='splitApply editorFormat';bar.prepend(formatSplit);
            const formatButton=document.createElement('button');formatButton.type='button';formatButton.className='button splitApplyMenu';formatButton.title='Image format';formatButton.setAttribute('aria-label','Image format');formatButton.setAttribute('aria-expanded','false');formatButton.innerHTML='<i class="cmsIcon cmsIcon-images"></i><i class="cmsIcon cmsIcon-chevron-down"></i>';formatSplit.append(formatButton);
            const formatList=document.createElement('div');formatList.className='splitApplyList imageEditorMenu';formatList.hidden=true;formatSplit.append(formatList);
            let formatLoading=false;
            for(const format of params.formats || []){
                const button=document.createElement('button');button.type='button';button.className='button transparent';button.textContent=format.label;
                button.classList.toggle('selected',format.key===media.media_crop_format);
                button.addEventListener('click',()=>{
                    if(formatLoading)return;
                    if(format.key===media.media_crop_format){closeSplitMenus();return;}
                    if(changed() && !confirm('Discard changes and switch format?'))return;
                    closeSplitMenus();
                    const src=params.src.replace(/(\/formats\/)[^/]+\//,'$1'+format.key+'/').split('?')[0];
                    formatLoading=true;const controls=[...bar.querySelectorAll('button')].filter(b=>!b.matches('[data-crop_cancel]')).map(b=>[b,b.disabled]);controls.forEach(([b])=>b.disabled=true);
                    runImageEditor({...params,src,replaceEditor:editor,onReplace:()=>{
                        close();
                        const fresh=new URL(src,document.baseURI);fresh.searchParams.set('_preview',Date.now());params.domObj[0].src=fresh.href;
                        const field=document.querySelector('select[name="imageEditorFormat"]');
                        if(field){field.value=format.key;field.dispatchEvent(new Event('change',{bubbles:true}));}
                    }}).catch(()=>{if(!editor.isConnected)return;formatLoading=false;controls.forEach(([b,disabled])=>b.disabled=disabled);});
                });formatList.append(button);
            }
            const all=control('Apply to all formats','layer-group',()=>apply(true));all.dataset.editorApply='';
            // Retain the existing split-Apply pattern rather than a second primary action.
            const applyButton=bar.querySelector('.submit');applyButton.innerHTML='<i class="cmsIcon cmsIcon-check" aria-hidden="true"></i>Apply';
            const split=document.createElement('div');split.className='splitApply';bar.append(split);split.append(applyButton);
            const cancel=bar.querySelector('[data-crop_cancel]');cancel.innerHTML='<i class="cmsIcon cmsIcon-xmark" aria-hidden="true"></i>';cancel.classList.add('transparent','icononly');cancel.title='Close';cancel.setAttribute('aria-label','Close');
            const commit=document.createElement('div');commit.className='editorCommit';bar.insertBefore(commit,split);commit.append(split,cancel);
            const menu=document.createElement('button');menu.type='button';menu.className='button submit splitApplyMenu';menu.title='Apply options';menu.setAttribute('aria-label','Apply options');menu.setAttribute('aria-expanded','false');menu.dataset.editorApply='';menu.innerHTML='<i class="cmsIcon cmsIcon-chevron-down"></i>';split.append(menu);
            const list=document.createElement('div');list.className='splitApplyList imageEditorMenu';list.hidden=true;split.append(list);list.append(all);
            all.classList.remove('submit');all.innerHTML='<i class="cmsIcon cmsIcon-layer-group"></i>Apply to all formats';
            // Image tools, utilities and Apply remain visually separate without a second toolbar.
            bar.insertBefore(resetSaved,bar.querySelector('[data-crop-grid]'));bar.insertBefore(aspectGroup,resetSaved);resetSaved.dataset.editorGroupStart='';commit.dataset.editorGroupStart='';
            // Menus share the global outside-click/keyboard handling. Colour controls may stay open in Crop.
            const manageTools=event=>{
                if(!event.target.closest('.splitApplyMenu,.splitApplyList'))closeSplitMenus();
            };
            bar.addEventListener('click',manageTools,true);
            cropTool.dataset.editorGroupStart='';bar.querySelector('.editorFormat').dataset.editorGroupEnd='';
            bar.querySelectorAll('.editorAdjustment')[2].dataset.editorGroupEnd='';

        }
        function keys(event){
            if(event.key==='Escape'&&!saving){
                event.preventDefault();event.stopImmediatePropagation();
                const open=[...document.querySelectorAll('.splitApplyList.is-open')].find(list=>editor.contains(list.splitHome));
                if(open){const opener=open.splitHome.querySelector('.splitApplyMenu');closeSplitMenus();opener?.focus({preventScroll:true});return;}
                if(unified && cropping){
                    if(cropStart){frame={...cropStart.frame};image={...cropStart.image};ratio=cropStart.ratio;}
                    cropStart=null;toggleCropping();return;
                }
                requestClose();
            }
            if(event.key==='Enter'&&!event.target.closest('button,input, [data-aspectratio]')){event.preventDefault();event.stopImmediatePropagation();apply();}
        }
        let sourceSize = {width: params.domObj[0].getBoundingClientRect().width};
        let sourceObserver;
        function followPage(){
            if(!params.inline || !editor.isConnected)return;
            const r=params.domObj[0].getBoundingClientRect();
            if (sourceSize.width > 0 && r.width > 0 && Math.abs(r.width-sourceSize.width) > .1) {
                const scale=r.width/sourceSize.width;
                frame={x:frame.x*scale,y:frame.y*scale,w:frame.w*scale,h:frame.h*scale};
                image={x:image.x*scale,y:image.y*scale,w:image.w*scale,h:image.h*scale};
            }
            sourceSize.width=r.width;
            origin={x:r.left+scrollX,y:r.top+scrollY};render();
            const bar=editor.querySelector('.cropBar');
            const content=params.domObj[0].closest('.cmsContent')?.getBoundingClientRect();
            const sidebar=document.querySelector('aside.cms')?.getBoundingClientRect();
            const left=Math.max(8,content?.left||8),right=Math.min(innerWidth-8,content?.right||innerWidth-8,sidebar?.width?sidebar.left:innerWidth-8);
            bar.style.maxWidth=Math.max(0,right-left-16)+'px';
            bar.style.left=(left+(right-left)/2)+'px';
            bar.style.top=Math.max(8,r.top-bar.offsetHeight-8)+'px';
        }
        function checkPage(){if(!params.domObj[0].isConnected)close();}
        function resize(){if(params.inline)followPage();else if(!saving)fit();}
        document.addEventListener('keydown',keys,true);
        document.addEventListener('pointermove',move,true);document.addEventListener('pointerup',up,true);document.addEventListener('pointercancel',cancelPointer,true);
        window.addEventListener('popstate',close,true);window.addEventListener('beforeunload',guard);window.addEventListener('resize',resize);
        if(params.startCropping && unified)toggleCropping?.();
        render();fit(true);
        if(params.inline){sourceObserver=new ResizeObserver(followPage);sourceObserver.observe(params.domObj[0]);followPage();document.addEventListener('scroll',followPage,true);document.addEventListener('cms:rendered',checkPage);}
    });
}
