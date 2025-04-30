<?
$file = <<<'FILE'
// 'Coordinates', a webgl framework
// Scott McGann - whitehotrobot@gmail.com
// all rights reserved - ©2025

const S = Math.sin, C = Math.cos, Rn = Math.random
var audioConsent = false
//new OffscreenCanvas(256, 256); * might be superior
const scratchCanvas = document.createElement('canvas')
const sctx = scratchCanvas.getContext('2d', {
    alpha                   : true,
    antialias               : true,
    imageSmoothingEnabled   : true,
    desynchronized          : true,
    premultipliedAlpha      : false
  }
)

const scratchImage = new Image()
const ModuleBase = 'https://srmcgann.github.io/Coordinates'

var cacheItem
const cache = {
  objFiles     : [],
  customShapes : [],
  textures     : [],
  geometry     : [],
  texImages    : []
}

const Renderer = async options => {

  var x=0, y=0, z=0
  var width = 1920, height = 1080
  var roll=0, pitch=0, yaw=0, fov=2e3
  var attachToBody = true, margin = 10, exportGPUSpecs = false
  var ambientLight = .2, alpha=false, clearColor = 0x000000
  var cameraMode = 'default', showCrosshair = false
  var crosshairSel = 0, crosshairMap = '', active = true
  var pageX, pageY, mouseX, mouseY, mouseButton
  var context = {
    mode: 'webgl2',
    options: {
      alpha                   : true,
      antialias               : true,
      desynchronized          : true,
      premultipliedAlpha      : false
     }
  }
  
  var alphaQueue = []
  var particleQueue = []
  var pointLights = []
  var pointLightCols = []
  var optionalPlugins = []
  
  if(typeof options != 'undefined'){
    Object.keys(options).forEach((key, idx) =>{
      switch(key.toLowerCase()){
        case 'plugins':
          options[key].map(option => {
            switch(option.type.toLowerCase()){
              case 'post processing': 
                if(typeof option.enabled == 'undefined' ||
                   !!option.enabled){
                  var pluginOption = {
                    name: option.type.toLowerCase(),
                    value: option.value.toLowerCase(),
                    enabled: typeof option.enabled == 'undefined' || !!option.enabled,
                    params: option?.params ?
                              option.params.map(v=>v.toLowerCase()) : [],
                  }
                  optionalPlugins.push( pluginOption )
                }
              break
              default:
              break
            }
          })
        break
        case 'width': width = options[key]; break
        case 'height': height = options[key]; break
        case 'alpha': alpha = options[key]; break
        case 'x': x = options[key]; break
        case 'y': y = options[key]; break
        case 'z': z = options[key]; break
        case 'roll': roll = options[key]; break
        case 'pitch': pitch = options[key]; break
        case 'yaw': yaw = options[key]; break
        case 'fov': fov = options[key]; break
        case 'clearcolor': clearColor = options[key]; break
        case 'attachtobody': attachToBody = !!options[key]; break
        case 'exportgpuspecs': exportGPUSpecs = !!options[key]; break
        case 'margin': margin = options[key]; break
        case 'cameramode': cameraMode = options[key]; break
        case 'ambientlight': ambientLight = options[key]; break
        case 'showcrosshair': showCrosshair = !!options[key]; break
        case 'crosshairsel': crosshairSel = options[key]; break
        case 'crosshairmap': crosshairMap = options[key]; break
        case 'context':
          context.mode = options[key].mode
          context.options = options[key]['options']
        break
        default:
        break
      }
    })
  }
  
  const c    = document.createElement('canvas')
  const ctx  = c.getContext(context.mode, context.options)
  c.width    = width
  c.height   = height
  c.tabIndex = 0
  c.style.outline = 'none'
  const contextType = context.mode

  if(context.mode != '2d') console.log(`GLSL version: ${ctx.getParameter(ctx.SHADING_LANGUAGE_VERSION)}`)
  if(exportGPUSpecs) getParams(ctx)
  
  switch(context.mode){
    case '2d':
    break
    default:
      ctx.viewport(0, 0, c.width, c.height)
    break
  }

  if(attachToBody){
    c.style.display    = 'block'
    c.style.position   = 'absolute'
    c.style.left       = '50vw'
    c.style.top        = '50vh'
    c.style.transform  = 'translate(-50%, -50%)'
    //c.style.border     = '1px solid #fff3'
    c.style.background = '#000'
    document.body.appendChild(c)
  }
  
  var rsz, ret
  window.addEventListener('resize', rsz = (e) => {
    var margin = ret.margin
    var b = document.body
    var n
    var d = c.width !== 0 ? c.height / c.width : 1
    if(b.clientHeight/b.clientWidth > d){
      c.style.width = `${(n=b.clientWidth) - margin*2}px`
      c.style.height = `${n*d - margin*2}px`
    }else{
      c.style.height = `${(n=b.clientHeight) - margin*2}px`
      c.style.width = `${n/d - margin*2}px`
    }
  })
  
  ret = {
    // vars & objects
    c, ctx, contextType, t:0, alpha,
    width, height, x, y, z,
    roll, pitch, yaw, fov,
    ready: false, ambientLight,
    pointLights, pointLightCols,
    particleQueue, alphaQueue, active,
    cameraMode, showCrosshair, crosshairSel,
    crosshairMap, pageX, pageY, mouseX, mouseY,
    mouseButton, rsz, margin, optionalPlugins
    
    // functions
    // ...
  }
  rsz()
  ret[contextType == '2d' ? 'ctx' : 'gl'] = ctx
  var renderer = ret
  
  
  const Clear = () => {
    switch(contextType){
      case '2d': 
        c.width = renderer.c.width
      break
      default:
        ctx.clearColor(0,0,0,1) //...HexToRGB(), 1.0)
        ctx.clear(ctx.COLOR_BUFFER_BIT)
        ctx.clear(ctx.DEPTH_BUFFER_BIT)
      break
    }
  }
  renderer['Clear'] = Clear
    
  const Draw = async (geometry, sortedPass = false, penumbraPass = false) => {
    var shader = geometry.shader
    var dset   = shader.datasets[geometry.datasetIdx]
    var sProg  = dset.program
    
    var equirectangularPlugin, omitSplitCheck
    renderer.optionalPlugins.map((plugin) => {
      switch(plugin.name) {
        case 'post processing':
          switch(plugin.value){
            case 'equirectangular':
              if(!!plugin.enabled){
                equirectangularPlugin = true
                renderer.equirectangularPlugin = true
                omitSplitCheck = !!(plugin.params.indexOf('omitsplitcheck') != -1)
              }
              break
            default:
              equirectangularPlugin = false
              renderer.equirectangularPlugin = false
            break
          }
        break
      }
    })
    
    if(typeof geometry?.shader != 'undefined'){
      
      // depth + alpha bugfix
      if(!sortedPass && (geometry.isSprite || (geometry.isLight && geometry.showSource))) {
        renderer.alphaQueue = [geometry, ...renderer.alphaQueue]
      }else{
        if(!sortedPass && geometry.isParticle ) {
          renderer.particleQueue = [geometry, ...renderer.particleQueue]
        }else{

          ctx.useProgram( sProg )

          
          for(var m = 0; m < ((equirectangularPlugin && !omitSplitCheck) ? 2 : 1); m++){
            
            // rotation mode
            ctx.uniform1i(dset.locRotationMode, geometry.rotationMode)
            
            // plugins
            renderer.locPlugin = ctx.getUniformLocation(dset.program, "plugin")
            renderer.locOmitSplitCheck = ctx.getUniformLocation(dset.program, "omitSplitCheck")
            renderer.locSplitCheckPass = ctx.getUniformLocation(dset.program, "splitCheckPass")
            ctx.uniform1f(renderer.locPlugin, equirectangularPlugin ? 1 : 0)
            ctx.uniform1f(renderer.locOmitSplitCheck, omitSplitCheck ? 1 : 0)
            ctx.uniform1f(renderer.locSplitCheckPass, m)
            
            if(geometry.showBounding) {
              var bounding = ShowBounding(geometry, renderer, geometry.showBounding,
                                          equirectangularPlugin, omitSplitCheck, m)
            }
            
            
            if(geometry.shapeType == 'particles') {

              renderer.ctx.blendFunc(ctx.ONE, ctx.SRC_ALPHA);
              renderer.ctx.enable(ctx.BLEND)
              
              ctx.uniform1f(dset.locPointSize,       geometry.size * (penumbraPass ? 3.0 : 1.0))
              ctx.uniform1f(dset.locIsParticle,      geometry.isParticle)
              ctx.uniform1f(dset.locPenumbraPass,    geometry.penumbraPass ? 1 : 0)
              
              ctx.uniform1f(dset.locT,               renderer.t)
              ctx.uniform1f(dset.locColorMix,        geometry.colorMix)
              ctx.uniform1f(dset.locIsSprite,        geometry.isSprite)
              ctx.uniform1f(dset.locIsLight,         geometry.isLight)
              
              ctx.uniform1f(dset.locCameraMode,      
                            renderer.cameraMode.toLowerCase() == 'fps' ? 1.0 : 0.0)
                            
              ctx.uniform1f(dset.locAlpha,           penumbraPass ?
                                                      geometry.alpha *
                                                        geometry.penumbra :
                                                      geometry.alpha)
              ctx.uniform3f(dset.locColor,           ...HexToRGB(geometry.color))
              ctx.uniform1f(dset.locAmbientLight,    ambLight / 8)
              ctx.uniform2f(dset.locResolution,      renderer.width, renderer.height)
              ctx.uniform3f(dset.locCamPos,          renderer.x, renderer.y, renderer.z)
              ctx.uniform3f(dset.locCamOri,          renderer.roll, renderer.pitch, renderer.yaw)
              ctx.uniform3f(dset.locGeoPos,          geometry.x, geometry.y, geometry.z)
              ctx.uniform3f(dset.locGeoOri,          geometry.roll, geometry.pitch, geometry.yaw)
              ctx.uniform1f(dset.locFov,             renderer.fov)
              ctx.uniform1f(dset.locEquirectangular, geometry.equirectangular ? 1.0 : 0.0)
              ctx.uniform1f(dset.locRenderNormals,   0)


              // vertices
              if(geometry?.vertices?.length){
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.vertex_buffer)
                ctx.bufferData(ctx.ARRAY_BUFFER, geometry.vertices, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.Vertex_Index_Buffer)
                ctx.bufferData(ctx.ELEMENT_ARRAY_BUFFER, geometry.vIndices, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.vertex_buffer)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.Vertex_Index_Buffer)
                dset.locPosition = ctx.getAttribLocation(dset.program, "position")
                ctx.vertexAttribPointer(dset.locPosition, 3, ctx.FLOAT, false, 0, 0)
                ctx.enableVertexAttribArray(dset.locPosition)
                ctx.drawElements(ctx.POINTS, geometry.vertices.length/3|0, ctx.UNSIGNED_INT,0)

                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, null)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, null)
              }
              
              renderer.ctx.blendFunc(ctx.ONE, ctx.ZERO)
              renderer.ctx.disable(ctx.BLEND)
              
            }else{  // not particles
              
              // update uniforms
              
              ctx.activeTexture(ctx.TEXTURE0)
              switch(geometry.textureMode){
                case 'video':
                  BindImage(ctx, dset.resource,  dset.texture, geometry.textureMode, renderer.t, geometry.map)
                break
                case 'canvas':
                  ctx.activeTexture(ctx.TEXTURE2)
                  BindImage(ctx, geometry.canvasTexture,  dset.texture, geometry.textureMode, renderer.t, geometry.map)
                break
                default:
                break
              }
              
              if(typeof geometry.canvasTexture != 'undefined'){
                ctx.activeTexture(ctx.TEXTURE2)
                BindImage(ctx, geometry.canvasTexture, dset.supplementalTexture, 'canvas', renderer.t, geometry.map)
                ctx.uniform1i(dset.locSupplementalTexture, 2)
                ctx.uniform1f(dset.locSupplementalTextureMix, geometry.canvasTextureMix)
              }
              

              ctx.activeTexture(ctx.TEXTURE0)
              //ctx.bindTexture(ctx.TEXTURE_2D, dset.texture)
              ctx.uniform1i(dset.locTexture, dset.texture)
              ctx.bindTexture(ctx.TEXTURE_2D, dset.texture)
              
              if(geometry.heightMap){
                ctx.activeTexture(ctx.TEXTURE4)
                ctx.uniform1i(dset.locHeightMap, 4)
                //ctx.bindTexture(ctx.TEXTURE_2D, dset.heightTexture)
                BindImage(ctx, dset.heightResource, dset.heightTexture, geometry.heightMapIsCanvas ? 'canvas' : geometry.heightTextureMode, renderer.t, geometry.heightMap)
                ctx.uniform1i(dset.locHeightTexture, dset.heightTexture)
                ctx.uniform1f(dset.locUseHeightMap, 1)
                ctx.uniform1f(dset.locHeightMapIntensity, geometry.heightMapIntensity)
                ctx.uniform1f(dset.locEquirectangularHeightmap, geometry.equirectangularHeightmap ? 1.0 : 0.0)
                ctx.bindTexture(ctx.TEXTURE_2D, dset.heightTexture)
                ctx.activeTexture(ctx.TEXTURE0)
              }else{
                ctx.uniform1f(dset.locUseHeightMap, 0)
              }
              
              
              // point lights
              ctx.uniform1i(dset.locPointLightCount, renderer.pointLights.length)
              var pldata = new Float32Array()
              var plcols = new Float32Array()
              renderer.pointLights.map(geometry => {
                pldata = [...pldata, geometry.x, geometry.y, geometry.z, geometry.lum]
                let col = HexToRGB(geometry.color)
                plcols = [...plcols, ...HexToRGB(geometry.color), 1.0]
              })
              if(pldata.length){
                ctx.uniform4fv(dset.locPointLights, pldata)
                ctx.uniform4fv(dset.locPointLightCols, plcols)
              }
              
              var ambLight = renderer.ambientLight

              dset.optionalLighting.map(lighting => {
                switch(lighting.name){
                  case 'ambientLight':
                    ambLight = lighting.value
                  break
                  default:
                  break
                }
              })
              
              ctx.useProgram( sProg )
              
              dset.optionalUniforms.map((uniform) => {
                if(typeof uniform?.loc === 'object'){
                  ctx[uniform.dataType](uniform.loc,      uniform.value)
                  ctx.uniform1f(uniform.locFlatShading,   uniform.flatShading ? 1.0 : 0.0)
                  switch(uniform.name){
                    case 'reflection':
                      ctx.activeTexture(ctx.TEXTURE1)
                      if(uniform.textureMode == 'video'){
                         BindImage(ctx, uniform.video,  uniform.refTexture, uniform.textureMode, renderer.t, uniform.map)
                      }
                      ctx.useProgram( sProg )
                      ctx.activeTexture(ctx.TEXTURE1)
                      ctx.uniform1i(uniform.locRefTexture, 1)
                      ctx.bindTexture(ctx.TEXTURE_2D, uniform.refTexture)
                      
                      ctx.uniform1f(uniform.locRefOmitEquirectangular,
                           ( geometry.shapeType == 'rectangle' ||
                             geometry.shapeType == 'point light' ||
                             geometry.shapeType == 'sprite' ) ? 1.0 : 0.0)
                    break
                    case 'phong':
                      uniform.locPhongTheta = ctx.getUniformLocation(dset.program, 'phongTheta')
                      ctx.uniform1f(uniform.locPhongTheta, uniform.theta + Math.PI)
                    break
                  }
                }
              })

              
              // other uniforms
              ctx.uniform1f(dset.locT,               renderer.t)
              ctx.uniform1f(dset.locIsParticle,      geometry.isParticle)
              ctx.uniform1f(dset.locPenumbraPass,    0)
              ctx.uniform1f(dset.locColorMix,        geometry.colorMix)
              ctx.uniform1f(dset.locIsSprite,        geometry.isSprite)
              ctx.uniform1f(dset.locIsLight,         geometry.isLight)
              
              ctx.uniform1f(dset.locCameraMode,      
                            renderer.cameraMode.toLowerCase() == 'fps' ? 1.0 : 0.0)
                            
              ctx.uniform1f(dset.locAlpha,           geometry.alpha)
              ctx.uniform3f(dset.locColor,           ...HexToRGB(geometry.color))
              ctx.uniform1f(dset.locAmbientLight,    ambLight / 8)
              ctx.uniform2f(dset.locResolution,      renderer.width, renderer.height)
              ctx.uniform3f(dset.locCamPos,          renderer.x, renderer.y, renderer.z)
              ctx.uniform3f(dset.locCamOri,          renderer.roll, renderer.pitch, renderer.yaw)
              ctx.uniform3f(dset.locGeoPos,          geometry.x, geometry.y, geometry.z)
              ctx.uniform3f(dset.locGeoOri,          geometry.roll, geometry.pitch, geometry.yaw)
              ctx.uniform1f(dset.locFov,             renderer.fov)
              ctx.uniform1f(dset.locEquirectangular, geometry.equirectangular ? 1.0 : 0.0)
              ctx.uniform1f(dset.locRenderNormals,   0)



              // bind buffers
              ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.uv_buffer)
              ctx.bufferData(ctx.ARRAY_BUFFER, geometry.uvs, ctx.STATIC_DRAW)
              ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.UV_Index_Buffer)
              ctx.bufferData(ctx.ELEMENT_ARRAY_BUFFER, geometry.uvIndices, ctx.STATIC_DRAW)
              ctx.vertexAttribPointer(dset.locUv , 2, ctx.FLOAT, false, 0, 0)
              ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, null)
              ctx.bindBuffer(ctx.ARRAY_BUFFER, null)


              //normals
              if(geometry?.normalVecs.length){
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.normalVec_buffer)
                ctx.bufferData(ctx.ARRAY_BUFFER, geometry.normalVecs, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.NormalVec_Index_Buffer)
                ctx.bufferData(ctx.ELEMENT_ARRAY_BUFFER, geometry.nIndices, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.normalVec_buffer)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.NormalVec_Index_Buffer)
                dset.locNormalVec= ctx.getAttribLocation(dset.program, "normalVec")
                ctx.vertexAttribPointer(dset.locNormalVec, 3, ctx.FLOAT, false, 0, 0)
                ctx.enableVertexAttribArray(dset.locNormalVec)

                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, null)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, null)
              }        
              

              // vertices
              if(geometry?.vertices?.length){
                
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.vertex_buffer)
                if(equirectangularPlugin){
                  var verts =  new Float32Array()
                  for(var i=0; i<geometry.vertices; i+=9){
                    var X1 = geometry.vertices[i+0]
                    var Y1 = geometry.vertices[i+1]
                    var Z1 = geometry.vertices[i+2]
                    var X2 = geometry.vertices[i+3]
                    var Y2 = geometry.vertices[i+4]
                    var Z2 = geometry.vertices[i+5]
                    var X3 = geometry.vertices[i+6]
                    var Y3 = geometry.vertices[i+7]
                    var Z4 = geometry.vertices[i+8]
                    
                  }
                  ctx.bufferData(ctx.ARRAY_BUFFER, verts, ctx.STATIC_DRAW)
                }else{
                  ctx.bufferData(ctx.ARRAY_BUFFER, geometry.vertices, ctx.STATIC_DRAW)
                }
                
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.Vertex_Index_Buffer)
                ctx.bufferData(ctx.ELEMENT_ARRAY_BUFFER, geometry.vIndices, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.vertex_buffer)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.Vertex_Index_Buffer)
                dset.locPosition = ctx.getAttribLocation(dset.program, "position")
                ctx.vertexAttribPointer(dset.locPosition, 3, ctx.FLOAT, false, 0, 0)
                ctx.enableVertexAttribArray(dset.locPosition)
                ctx.drawElements(geometry.wireframe ? ctx.LINE_STRIP :
                                    ctx.TRIANGLES,
                                  geometry.vertices.length/3|0,
                                  ctx.UNSIGNED_INT,0)

                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, null)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, null)
              }

              // normals lines drawn, optionally
              ctx.uniform1f(dset.locRenderNormals, geometry.showNormals ? 1 : 0)
              if(geometry.showNormals && geometry?.normals?.length){
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.normal_buffer)
                ctx.bufferData(ctx.ARRAY_BUFFER, geometry.normals, ctx.STATIC_DRAW)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, geometry.Normal_Index_Buffer)
                ctx.bufferData(ctx.ELEMENT_ARRAY_BUFFER, geometry.nIndices, ctx.STATIC_DRAW)
                ctx.vertexAttribPointer(dset.locNormal, 3, ctx.FLOAT, false, 0, 0)
                dset.locNormal = ctx.getAttribLocation(dset.program, "normal")
                ctx.bindBuffer(ctx.ARRAY_BUFFER, geometry.normal_buffer)
                ctx.bufferData(ctx.ARRAY_BUFFER, geometry.normals, ctx.STATIC_DRAW)
                ctx.enableVertexAttribArray(dset.locNormal)
                ctx.drawElements(ctx.LINES, geometry.normals.length/3|0, ctx.UNSIGNED_INT,0)
                ctx.bindBuffer(ctx.ELEMENT_ARRAY_BUFFER, null)
                ctx.bindBuffer(ctx.ARRAY_BUFFER, null)
              }
            }
          }
        }
      }
    }
  }
  renderer['Draw'] = Draw

  renderer.nullShader = await BasicShader(renderer, [
    {uniform: {type: 'phong', value: 0} }
  ] )
        
  renderer.particleShader = await BasicShader(renderer, [] )
  
  window.addEventListener('mousemove', e => {
    var rect = renderer.c.getBoundingClientRect()
    renderer.mouseX = (e.pageX-rect.left) / renderer.c.clientWidth * renderer.width
    renderer.mouseY = (e.pageY-rect.top) / renderer.c.clientHeight * renderer.height
  })
  window.addEventListener('mousedown', e => {
    renderer.mouseButton = e.buttons
  })
  window.addEventListener('mouseup', e => {
    renderer.mouseButton = -1
  })
  
  return renderer
}

const ResizeRenderer = async (renderer, width, height) => {
  renderer.width = width
  renderer.height = height
  renderer.c.width = width
  renderer.c.height = height
  renderer.rsz()
  switch(renderer.ctx.mode){
    case '2d':
    break
    default:
      renderer.ctx.viewport(0, 0, renderer.c.width, renderer.c.height)
    break
  }
}

const DestroyRenderer = async (renderer) => {
  renderer.c.remove()
  renderer.active = false
}

const DestroyShape = shape => {
  switch(shape.shapeType){
    case 'point light':
      shape.renderer.pointLights=shape.renderer.pointLights.filter((v,i)=>i!=shape.pointLightID)
      shape.renderer.pointLights.map((v, i) => v.pointLightID = i)
    break
    default:
    break
  }
}

const LoadOBJ = async (url, scale, tx, ty, tz, rl, pt, yw, recenter=true, involveCache=true) => {

  var ret = { vertices: [], normals: [], uvs: [] }
  var a, X, Y, Z
  if(involveCache && (cacheItem = cache.objFiles.filter(v=>v.url == url)).length){
    ret = cacheItem[0].ret
  }else{
    var vInd = new Float32Array()
    var nInd = new Float32Array()
    var uInd = new Float32Array()
    var fInd = new Float32Array()
    await fetch(url).then(res=>res.text()).then(data => {
      data.split("\n").forEach(line => {
        var lineParts = line.split(' ')
        var lineType = lineParts[0]
        switch(lineType){
          case 'v':
            lineParts.shift()
            vInd = [...vInd, lineParts.map(v=>+v)]
          break
          case 'vt':
            lineParts.shift()
            uInd = [...uInd, lineParts.map(v=>+v)]
          break
          case 'vn':
            lineParts.shift()
            nInd = [...nInd, lineParts.map(v=>+v)]
          break
          case 'f':
            lineParts.shift()
            fInd = [...fInd, lineParts.map(v=>v)]
          break
        }
      })
      fInd.map(face => {
        var v = new Float32Array(), u = new Float32Array(), n = new Float32Array()
        var vidx, uidx, nidx
        var useUVs = false, useNormals = false
        face.map(vertex => {
          var vertexParts = vertex.split('/')
          switch(vertexParts.length){
            case 1: // only verts
              vidx = vertexParts[0]
              v = [...v, vInd[vidx-1]]
            break
            case 2: // verts, uvs
              vidx = vertexParts[0]
              uidx = vertexParts[1]
              v = [...v, vInd[vidx-1]]
              u = [...u, uInd[uidx-1]]
              useUVs = true
            break
            case 3: // verts, uvs, normals
              vidx = vertexParts[0]
              uidx = vertexParts[1]
              nidx = vertexParts[2]
              v = [...v, vInd[vidx-1]]
              u = [...u, uInd[uidx-1]]
              n = [...n, nInd[nidx-1]]
              useNormals = true
            break
          }
        })
        var X1 = v[0][0]
        var Y1 = v[0][1]
        var Z1 = v[0][2]
        var X2 = v[1][0]
        var Y2 = v[1][1]
        var Z2 = v[1][2]
        var X3 = v[2][0]
        var Y3 = v[2][1]
        var Z3 = v[2][2]
        if(useNormals){
          var NX1 = n[0][0]
          var NY1 = n[0][1]
          var NZ1 = n[0][2]
          var NX2 = n[1][0]
          var NY2 = n[1][1]
          var NZ2 = n[1][2]
          var NX3 = n[2][0]
          var NY3 = n[2][1]
          var NZ3 = n[2][2]
        }
        if(v.length == 4){
          var X4 = v[3][0]
          var Y4 = v[3][1]
          var Z4 = v[3][2]
          if(useNormals){
            var NX4 = n[3][0]
            var NY4 = n[3][1]
            var NZ4 = n[3][2]
          }
        }
        
        switch(v.length) {
          case 3:
            a = new Float32Array()
            n.map((q, j) => {
              a = [...a,
               [X1,Y1,Z1, X1+NX1, Y1+NY1, Z1+NZ1],
               [X2,Y2,Z2, X2+NX2, Y2+NY2, Z2+NZ2],
               [X3,Y3,Z3, X3+NX3, Y3+NY3, Z3+NZ3]]
            })
            n = a
            ret.vertices = [...ret.vertices,
                            ...v[0], ...v[1], ...v[2]]
            if(u.length && typeof u[0] != 'undefined')
              ret.uvs      = [...ret.uvs,
                            ...u[0], ...u[1], ...u[2]]
            if(n.length && typeof n[0] != 'undefined')
              ret.normals  = [...ret.normals,
                            ...n[0], ...n[1], ...n[2]]
          break
          case 4: // split quad
            a = new Float32Array()
            n.map((q, j) => {
              a = [...a,
               [X1,Y1,Z1, X1+NX1, Y1+NY1, Z1+NZ1],
               [X2,Y2,Z2, X2+NX2, Y2+NY2, Z2+NZ2],
               [X3,Y3,Z3, X3+NX3, Y3+NY3, Z3+NZ3],
               [X4,Y4,Z4, X4+NX4, Y4+NY4, Z4+NZ4]]
            })
            n = a
            ret.vertices          = [...ret.vertices,
                            ...v[0], ...v[1], ...v[2],
                            ...v[2], ...v[3], ...v[0]]
            if(u.length) ret.uvs  = [...ret.uvs,
                            ...u[0], ...u[1], ...u[2],
                            ...u[2], ...u[3], ...u[0]]
            if(n.length) ret.normals = [...ret.normals,
                            ...n[0], ...n[1], ...n[2],
                            ...n[2], ...n[3], ...n[0]]
          break
        }
      })
    })
    cache.objFiles = [...structuredClone(cache.objFiles), {url, ret}]
  }
  for(var i = 0; i<ret.uvs.length; i+=2){
    ret.uvs[i+1] = 1-ret.uvs[i+1]
  }
  for(var i = 0; i<ret.normals.length; i+=3){
    ret.normals[i+1] = -ret.normals[i+1]
  }
  for(var i = 0; i<ret.vertices.length; i+=3){
    X = ret.vertices[i+0]
    Y = ret.vertices[i+1]
    Z = ret.vertices[i+2]
    var ar = [X,Y,Z]
    R_pyr(...ar, {roll:rl, pitch:pt, yaw:yw})
    ret.vertices[i+0] = ar[0]
    ret.vertices[i+1] = ar[1]
    ret.vertices[i+2] = ar[2]

    for(var m = 2; m--;){
      var l = m ? i*2 : i*2+3
      X = ret.normals[l+0]
      Y = ret.normals[l+1]
      Z = ret.normals[l+2]
      var ar = [X,Y,Z]
      ar = R_rpy(...ar, {roll:rl, pitch:pt, yaw:yw})
      ret.normals[l+0] = ar[0]
      ret.normals[l+1] = ar[1]
      ret.normals[l+2] = ar[2]
    }
    
  }
  return ret
}

const Q = (X, Y, Z, c, AR=700) => [c.width/2+X/Z*AR, c.height/2+Y/Z*AR]

const R = (X,Y,Z, cam, m=false) => {
  var M = Math, p, d
  var H=M.hypot, A=M.atan2
  var Rl = cam.roll, Pt = cam.pitch, Yw = cam.yaw
  X = S(p=A(X,Y)+Rl)*(d=H(X,Y))
  Y = C(p)*d
  X = S(p=A(X,Z)+Yw)*(d=H(X,Z))
  Z = C(p)*d
  Y = S(p=A(Y,Z)+Pt)*(d=H(Y,Z))
  Z = C(p)*d
  if(m){
    var oX = cam.x, oY = cam.y, oZ = cam.z
    X += oX
    Y += oY
    Z += oZ
  }
  return [X, Y, Z]
}

const R_ypr = (X,Y,Z, cam, m=false) => {
  var M = Math, p, d
  var H=M.hypot, A=M.atan2
  var Rl = cam.roll, Pt = cam.pitch, Yw = cam.yaw
  X = S(p=A(X,Z)+Yw)*(d=H(X,Z))
  Z = C(p)*d
  Y = S(p=A(Y,Z)+Pt)*(d=H(Y,Z))
  Z = C(p)*d
  X = S(p=A(X,Y)+Rl)*(d=H(X,Y))
  Y = C(p)*d
  if(m){
    var oX = cam.x, oY = cam.y, oZ = cam.z
    X += oX
    Y += oY
    Z += oZ
  }
  return [X, Y, Z]
}

const R_pyr = (X,Y,Z, cam, m=false) => {
  var M = Math, p, d
  var H=M.hypot, A=M.atan2
  var Rl = cam.roll, Pt = cam.pitch, Yw = cam.yaw
  Y = S(p=A(Y,Z)+Pt)*(d=H(Y,Z))
  Z = C(p)*d
  X = S(p=A(X,Z)+Yw)*(d=H(X,Z))
  Z = C(p)*d
  X = S(p=A(X,Y)+Rl)*(d=H(X,Y))
  Y = C(p)*d
  if(m){
    var oX = cam.x, oY = cam.y, oZ = cam.z
    X += oX
    Y += oY
    Z += oZ
  }
  return [X, Y, Z]
}

const R_rpy = (X,Y,Z, cam, m=false) => {
  var M = Math, p, d
  var H=M.hypot, A=M.atan2
  var Rl = cam.roll, Pt = cam.pitch, Yw = cam.yaw
  X = S(p=A(X,Y)+Rl)*(d=H(X,Y))
  Y = C(p)*d
  Y = S(p=A(Y,Z)+Pt)*(d=H(Y,Z))
  Z = C(p)*d
  X = S(p=A(X,Z)+Yw)*(d=H(X,Z))
  Z = C(p)*d
  if(m){
    var oX = cam.x, oY = cam.y, oZ = cam.z
    X += oX
    Y += oY
    Z += oZ
  }
  return [X, Y, Z]
}

const LoadGeometry = async (renderer, geoOptions) => {

  var objX, objY, objZ, objRoll, objPitch, objYaw
  var vertex_buffer, Vertex_Index_Buffer
  var normal_buffer, Normal_Index_Buffer, video
  var normalVec_buffer, NormalVec_Index_Buffer
  var uv_buffer, UV_Index_Buffer, name, shapeType
  var vIndices, nIndices, nVecIndices, uvIndices
  var canvasTexture, canvasTextureMix, showBounding
  var boundingColor
  const gl = renderer.gl
  var shape, exportShape = false
  
  // geo defaults
  var x = 0, y = 0, z = 0
  var roll = 0, pitch = 0, yaw = 0
  var scaleX=1, scaleY=1, scaleZ=1
  var scaleUVX  = 1, scaleUVY  = 1
  var offsetUVX = 0, offsetUVY = 0
  var rows             = 16
  var cols             = 40
               // must remain "16, 40" to trigger default quick torus/cylinder
  
  var url                    = ''
  var name                   = ''
  var size                   = 1
  var averageNormals         = false
  var subs                   = 0
  var sphereize              = 0
  var color                  = 0x333333
  var colorMix               = .1
  var equirectangular        = -1
  var rotationMode           = 0
  var equirectangularHeightmap = -1
  var preComputeNormalAssocs = false
  var flipNormals            = false
  var showNormals            = false
  var map                    = '' //`${ModuleBase}/resources/flat_grey.jpg`
  var heightMap              = ''
  var heightMapIntensity     = 1
  var heightMapIsCanvas  = false
  var canvasTextureMix       = -1
  var muted                  = true
  var boundingColor          = 0x88ff22
  var showBounding           = false
  var isSprite               = 0.0
  var isLight                = 0.0
  var isParticle             = 0.0
  var wireframe              = false
  var penumbra               = 0.0
  var playbackSpeed          = 1.0
  var involveCache           = true
  var textureMode            = 'image'
  var showSource             = false
  var disableDepthTest       = false
  var lum                    = 1
  var alpha                  = 1
  var geometryData           = new Float32Array()  // for dynamic shape
  var texCoords              = new Float32Array()  // for dynamic shape
  
  var geometry = {}
  
  
  // must precede
  Object.keys(geoOptions).forEach((key, idx) => {
    switch(key.toLowerCase()){
      case 'showsource':
        showSource = !!geoOptions[key]; break
    }
  })
  Object.keys(geoOptions).forEach((key, idx) => {
    switch(key.toLowerCase()){
      case 'x'               : x = geoOptions[key]; break
      case 'y'               : y = geoOptions[key]; break
      case 'z'               : z = geoOptions[key]; break
      case 'roll'            : roll = geoOptions[key]; break
      case 'pitch'           : pitch = geoOptions[key]; break
      case 'yaw'             : yaw = geoOptions[key]; break
      case 'shapetype'       :
        shapeType = geoOptions[key].toLowerCase();
        switch(shapeType){
          case 'sprite':
            map = `${ModuleBase}/resources/sprite.png`
          break
          case 'point light':
            map = showSource ? 
              `${ModuleBase}/resources/stars/star.png` : ''
          break
        }
      break
      case 'size'               : size = geoOptions[key]; break
      case 'subs'               : subs = geoOptions[key]; break
      case 'equirectangular'    : equirectangular = !!geoOptions[key]; break
      case 'equirectangularheightmap' : equirectangularHeightmap = !!geoOptions[key]; break
      case 'flipnormals'        : flipNormals = !!geoOptions[key]; break
      case 'shownormals'        : showNormals = !!geoOptions[key]; break
      case 'sphereize'          : sphereize = geoOptions[key]; break
      case 'rotationmode'       : rotationMode = geoOptions[key]; break
      case 'objx'               : objX = geoOptions[key]; break
      case 'objy'               : objY = geoOptions[key]; break
      case 'objz'               : objZ = geoOptions[key]; break
      case 'objroll'            : objRoll = geoOptions[key]; break
      case 'objpitch'           : objPitch = geoOptions[key]; break
      case 'objyaw'             : objYaw = geoOptions[key]; break
      case 'scaleuvx'           : scaleUVX = geoOptions[key]; break
      case 'scaleuvy'           : scaleUVY = geoOptions[key]; break
      case 'offsetuvx'          : offsetUVX = geoOptions[key]; break
      case 'offsetuvy'          : offsetUVY = geoOptions[key]; break
      case 'scalex'             : scaleX = geoOptions[key]; break
      case 'scaley'             : scaleY = geoOptions[key]; break
      case 'scalez'             : scaleZ = geoOptions[key]; break
      case 'wireframe'          : wireframe = !!geoOptions[key]; break
      case 'name'               : name = geoOptions[key]; break
      case 'color'              : color = geoOptions[key]; break
      case 'colormix'           : colorMix = geoOptions[key]; break
      case 'exportshape'        : exportShape = !!geoOptions[key]; break
      case 'penumbra'           : penumbra = geoOptions[key]; break
      case 'url'                : url = geoOptions[key]; break
      case 'map'                : map = geoOptions[key]; break
      case 'heightmap'          : heightMap = geoOptions[key]; break
      case 'heightmapintensity' : heightMapIntensity = geoOptions[key]; break
      case 'heightmapiscanvas'  : heightMapIsCanvas= !!geoOptions[key]; break
      case 'rows'               : rows = geoOptions[key]; break
      case 'disabledepthtest'   : disableDepthTest = geoOptions[key]; break
      case 'cols'               : cols = geoOptions[key]; break
      case 'canvastexturemix'   : canvasTextureMix = geoOptions[key]; break
      case 'canvastexture'      :
        canvasTexture = geoOptions[key]
        textureMode = 'canvas'
        break
      case 'involvecache'       : involveCache = !!geoOptions[key]; break
      case 'muted'              : muted = !!geoOptions[key]; break
      case 'lum'                : lum = geoOptions[key]; break
      case 'alpha'              : alpha = geoOptions[key]; break
      case 'geometrydata'       : geometryData = geoOptions[key]; break
      case 'precomputenormalassocs' : preComputeNormalAssocs = geoOptions[key]; break
      case 'texcoords'          : texCoords = geoOptions[key]; break
      case 'boundingcolor'      : boundingColor = geoOptions[key]; break
      case 'showbounding'       : showBounding = !!geoOptions[key]; break
      case 'issprite'           :
        isSprite = (!!geoOptions[key]) ? 1.0: 0.0; break
      case 'islight'            :
        isLight = (!!geoOptions[key]) ? 1.0: 0.0; break
      case 'issprite'           :
        isParticle = (!!geoOptions[key]) ? 1.0: 0.0; break
      case 'playbackspeed'      :
        playbackSpeed = geoOptions[key]; break
      case 'averagenormals'     :
        averageNormals = geoOptions[key];
        preComputeNormalAssocs = averageNormals
      break
      default:
        geometry[key] = geoOptions[key]
      break
    }
  })
  
  var tempCanvas1, tempCanvas2
  if(typeof geoOptions.canvasTexture != 'undefined'){
    if(canvasTextureMix == -1) canvasTextureMix = 1
    tempCanvas1 = geoOptions.canvasTexture
    delete geoOptions.canvasTexture
  }else{
    canvasTextureMix = 0
  }
  if(geoOptions.heightMapIsCanvas){
    tempCanvas2 = geoOptions.heightMap
    delete geoOptions.heightMap
  }

  geoOptions = structuredClone(geoOptions)

  if(typeof tempCanvas1 != 'undefined'){
    geoOptions.canvasTexture = tempCanvas1
  }
  if(typeof tempCanvas2 != 'undefined'){
    geoOptions.heightMap = tempCanvas2
  }
  
  
  //if(sphereize) averageNormals = true

  var vertices          = new Float32Array()
  var normals           = new Float32Array()
  var normalVecs        = new Float32Array()
  var uvs               = new Float32Array()

  var fileURL, hint
  var resolved          = false
  var resolvedFromCache = false
  
  if(shapeType.indexOf('custom shape') != -1){
    fileURL = url
    hint = `${shapeType} ${name} (${url})`
  }else{
    hint = `${shapeType}_${subs}`
    if(subs < 5 && hint){
      var fileBase
      switch(hint){
        case 'tetrahedron_0':
        case 'tetrahedron_1':
        case 'tetrahedron_2':
        case 'tetrahedron_3':
        case 'tetrahedron_4':
        case 'cube_0':
        case 'cube_1':
        case 'cube_2':
        case 'cube_3':
        case 'cube_4':
        case 'octahedron_0':
        case 'octahedron_1':
        case 'octahedron_2':
        case 'octahedron_3':
        case 'octahedron_4':
        case 'dodecahedron_0':
        case 'dodecahedron_1':
        case 'dodecahedron_2':
        case 'dodecahedron_3':
        case 'dodecahedron_4':
        case 'icosahedron_0':
        case 'icosahedron_1':
        case 'icosahedron_2':
        case 'icosahedron_3':
        case 'icosahedron_4':
        case 'cylinder_0':
        case 'torus_0':
        case 'torus knot_0':
          if(hint != 'cylinder_0' || hint != 'torus_0' ||
             (hint == 'cylinder_0' && rows == 16 && cols == 40) ||
             (hint == 'torus_0' && rows == 16 && cols == 40) ||
             (hint == 'torus knot_0' && rows == 16 && cols == 40) 
             ){
            resolved = true;
            url = `${ModuleBase}/new%20shapes/`
            fileURL = `${url}${hint}.json`
            if(involveCache && (cacheItem = cache.geometry.filter(v=>v.url==fileURL)).length){
              console.log(`found geometry (${hint}) in cache... using it`)
              var data          = cacheItem[0].data
              vertices          = new Float32Array(data.vertices)
              normals           = new Float32Array(data.normals)
              normalVecs        = new Float32Array(data.normalVecs)
              uvs               = new Float32Array(data.uvs)
              resolvedFromCache = true
              resolved = true
            }else{
              await fetch(fileURL).then(res=>res.json()).then(data => {
                vertices    = data.vertices
                normals     = data.normals
                normalVecs  = data.normalVecs
                uvs         = data.uvs
                cache.geometry.push({data: structuredClone(data), url: fileURL})
              })
              resolved = true
            }
          }else{
            // unresolved shape
          }
        break
      }
    }
  }
  if(!resolved){
    // involve cache
    switch(shapeType){
      case 'custom shape':
        if(involveCache && (cacheItem = cache.customShapes.filter(v=>v.url==url)).length){
          console.log(`found custom shape in cache... using it`)
          var data   = cacheItem[0].data
          vertices   = data.vertices
          normals    = data.normals
          normalVecs = data.normalVecs
          uvs        = data.uvs
          resolved = true
          resolvedFromCache = true
        }
        if(!resolved){
          await fetch(fileURL).then(res=>res.json()).then(data=>{
            vertices    = data.vertices
            normals     = data.normals
            normalVecs  = data.normalVecs
            uvs         = data.uvs
            resolved    = true
            cache.customShapes.push({data: structuredClone(data), url})
          })
        }
      break
      default: break
    }
  }

  if(!resolved){
    switch(shapeType){
      case 'tetrahedron':
        if(equirectangular == -1) equirectangular = true
        if(equirectangularHeightmap == -1) equirectangularHeightmap = true
        shape = await Tetrahedron(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'octahedron':
        if(equirectangular == -1) equirectangular = true
        if(equirectangularHeightmap == -1) equirectangularHeightmap = true
        shape = await Octahedron(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'icosahedron':
        if(equirectangular == -1) equirectangular = true
        if(equirectangularHeightmap == -1) equirectangularHeightmap = true
        shape = await Icosahedron(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'torus':
        shape = await Torus(size, subs, sphereize,
                      flipNormals, shapeType, rows, cols)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'torus knot':
        shape = await TorusKnot(size, subs, rows, cols, sphereize,
                      flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'cylinder':
        shape = await Cylinder(size, subs, rows, cols, sphereize,
                      flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'dynamic':
        shape = await GeometryFromRaw(geometryData, texCoords,
            size, subs, sphereize, flipNormals,
            !!geometryData.filter(v=>v.length==4).length,
            shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          if(typeof v.texCoord != 'undefined' && v.texCoord.length)
            uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'cube':
        shape = await Cube(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'rectangle':
        shape = await Rectangle(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'sprite':
        isSprite = true
        shape = await Rectangle(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'particles':
        isParticle = 1.0
        geometryData.map(v => {
          vertices = [...vertices, ...v]
        })
      break
      case 'point light':
        isLight = true
        if(!showSource){
          shape = { geometry: [] }
        }else{
          shape = await Rectangle(Math.max(size, .5) , subs+1, sphereize, flipNormals, shapeType)
        }
        shape.geometry.map(v => {
          vertices = [...vertices, ...v.position]
          normals  = [...normals,  ...v.normal]
          uvs      = [...uvs,      ...v.texCoord]
        })
      break
      case 'obj':
        if(typeof objX     == 'undefined') objX     = 0
        if(typeof objY     == 'undefined') objY     = 0
        if(typeof objZ     == 'undefined') objZ     = 0
        if(typeof objRoll  == 'undefined') objRoll  = 0
        if(typeof objPitch == 'undefined') objPitch = 0
        if(typeof objYaw   == 'undefined') objYaw   = 0
        shape = await LoadOBJ(url, size, objX, objY, objZ,
                              objRoll, objPitch, objYaw, false)
        vertices = shape.vertices
        normals  = shape.normals
        uvs      = shape.uvs
      break
      case 'dodecahedron':
        if(equirectangular == -1) equirectangular = true
        if(equirectangularHeightmap == -1) equirectangularHeightmap = true
        shape = await Dodecahedron(size, subs, sphereize, flipNormals, shapeType)
        shape.geometry.map(v => {
          vertices    = [...vertices, ...v.position]
          normals     = [...normals,  ...v.normal]
          uvs         = [...uvs,      ...v.texCoord]
        })
      break
    }
    
    /*for(var i=0; i<vertices.length; i+=3){
       vertices[i+0] *= scaleX
       vertices[i+1] *= scaleY
       vertices[i+2] *= scaleZ
    }
    
    // scale normals, with shape if scaled
    if(1||shapeType != 'obj' &&
       shapeType != 'customShape'){
         for(var i=0; i<normals.length; i+=6){
        var nx = normals[i+3] - normals[i+0]
        var ny = normals[i+4] - normals[i+1]
        var nz = normals[i+5] - normals[i+2]
        normals[i+0] *= scaleX
        normals[i+1] *= scaleY
        normals[i+2] *= scaleZ
        normals[i+3] = normals[i+0] + nx
        normals[i+4] = normals[i+1] + ny
        normals[i+5] = normals[i+2] + nz
      }
    }*/
  }else{
    switch(shapeType){
      case 'tetrahedron': case 'octahedron':
      case 'dodecahedron': case 'icosahedron':
      if(equirectangular == -1) equirectangular = true
      if(equirectangularHeightmap == -1) equirectangularHeightmap = true
      break
    }
  }

  if(offsetUVX != 0 || offsetUVY != 0) {
    for(var i = 0; i<uvs.length; i+=2){
      uvs[i+0] += offsetUVX
      uvs[i+1] += offsetUVY
    }
  }
  
  if(scaleUVX != 1 || scaleUVY != 1) {
    for(var i = 0; i<uvs.length; i+=2){
      uvs[i+0] *= scaleUVX
      uvs[i+1] *= scaleUVY
    }
  }
  
  //sphereize
  if(shapeType != 'particles' &&
     (shapeType != 'custom shape' &&
     shapeType != 'obj' ||
     sphereize || scaleX || scaleY || scaleZ)){
    var ip1 = sphereize
    var ip2 = 1 -sphereize
    for(var i = 0; i< vertices.length; i+=3){
      var d, val
    
      var X = vertices[i+0]
      var Y = vertices[i+1]
      var Z = vertices[i+2]
      d = Math.hypot(X,Y,Z) + .0001
      X /= d
      Y /= d
      Z /= d
      X *= ip1 + d*ip2
      Y *= ip1 + d*ip2
      Z *= ip1 + d*ip2
      vertices[i+0] = X * size * scaleX
      vertices[i+1] = Y * size * scaleY
      vertices[i+2] = Z * size * scaleZ
      
      var ox = normals[i*2+0]
      var oy = normals[i*2+1]
      var oz = normals[i*2+2]

      normals[i*2+0] += vertices[i+0] - ox
      normals[i*2+1] += vertices[i+1] - oy
      normals[i*2+2] += vertices[i+2] - oz
      normals[i*2+3] += vertices[i+0] - ox
      normals[i*2+4] += vertices[i+1] - oy
      normals[i*2+5] += vertices[i+2] - oz

      //normals[i*2+0] *= scaleX
      //normals[i*2+1] *= scaleY
      //normals[i*2+2] *= scaleZ
      //normals[i*2+3] *= scaleX
      //normals[i*2+4] *= scaleY
      //normals[i*2+5] *= scaleZ
    }
  }
  if(objX || objY || objZ || objRoll || objPitch || objYaw){
    if(typeof objX     == 'undefined') objX     = 0
    if(typeof objY     == 'undefined') objY     = 0
    if(typeof objZ     == 'undefined') objZ     = 0
    if(typeof objRoll  == 'undefined') objRoll  = 0
    if(typeof objPitch == 'undefined') objPitch = 0
    if(typeof objYaw   == 'undefined') objYaw   = 0
    var ar
    for(var i = 0; i< normals.length; i+=6){
      x = normals[i+0]
      Y = normals[i+1]
      Z = normals[i+2]
      ar = R(X, Y, Z, {roll: objRoll, pitch: objPitch, yaw: objYaw}, false)
      normals[i+0] = ar[0] + objX
      normals[i+1] = ar[1] + objY
      normals[i+2] = ar[2] + objZ
      x = normals[i+3]
      Y = normals[i+4]
      Z = normals[i+5]
      ar = R(X, Y, Z, {roll: objRoll, pitch: objPitch, yaw: objYaw}, false)
      normals[i+3] = ar[0] + objX
      normals[i+4] = ar[1] + objY
      normals[i+5] = ar[2] + objZ
    }
    for(var i = 0; i< vertices.length; i+=3){
      x = vertices[i+0]
      Y = vertices[i+1]
      Z = vertices[i+2]
      ar = R(X, Y, Z, {roll: objRoll, pitch: objPitch, yaw: objYaw}, false)
      vertices[i+0] = ar[0] + objX
      vertices[i+1] = ar[1] + objY
      vertices[i+2] = ar[2] + objZ
    }
  }
  
  if(averageNormals) AverageNormals(vertices, normals, shapeType)


  if(shapeType == 'dynamic' || preComputeNormalAssocs){
    // pre-compute coincidental normals for averaging
    geometry.normalAssocs = new Float32Array()
      for(var i = 0; i < vertices.length; i+=3){
      var X1 = vertices[i+0]
      var Y1 = vertices[i+1]
      var Z1 = vertices[i+2]
      var a = new Float32Array()
      for(var j = 0; j < vertices.length; j+=3){
        var X2 = vertices[j+0]
        var Y2 = vertices[j+1]
        var Z2 = vertices[j+2]
        
        if(Math.hypot(X1-X2, Y1-Y2, Z1-Z2) < .001){
          a = [...a, j]
        }
      }
      geometry.normalAssocs= [...geometry.normalAssocs, a]
    }
  }

    
  if(!resolvedFromCache || !resolved || averageNormals || exportShape){
    normalVecs    = new Float32Array()
    for(var i=0; i<normals.length; i+=6){
      let X = normals[i+3] - normals[i+0]
      let Y = normals[i+4] - normals[i+1]
      let Z = normals[i+5] - normals[i+2]
      normalVecs = [...normalVecs, X,Y,Z]
    }
  }
  
  if(flipNormals && !exportShape){
    for(var i=0; i<normals.length; i+=6){
      normals[i+3] = normals[i+0] - (normals[i+3]-normals[i+0])
      normals[i+4] = normals[i+1] - (normals[i+4]-normals[i+1])
      normals[i+5] = normals[i+2] - (normals[i+5]-normals[i+2])
    }
    for(var i=0; i<normalVecs.length; i+=3){
      normalVecs[i+0] *= -1
      normalVecs[i+1] *= -1
      normalVecs[i+2] *= -1
    }
  }
  
  if(exportShape && name !== 'background'){
    var popup = document.createElement('div')
    popup.style.position = 'fixed'
    popup.style.zIndex = 100000
    popup.style.left = '50%'
    popup.style.top = '50%'
    popup.style.transform = 'translate(-50%, -50%)'
    popup.style.background = '#0008'
    popup.style.padding = '20px'
    popup.style.width = '600px'
    popup.style.height = '350px'
    popup.style.border = '1px solid #fff4'
    popup.style.borderRadius = '5px'
    popup.style.fontFamily = 'monospace'
    popup.style.fontSize = '20px'
    popup.style.color = '#fff'
    var titleEl = document.createElement('div')
    titleEl.style.fontSize = '24px'
    titleEl.style.color = '#0f8c'
    titleEl.innerHTML = `Export Coordinates File -> ${shapeType} ` + (geoOptions?.name ? `(${geoOptions.name})` : '') + '<br><br>'
    popup.appendChild(titleEl)
    var output = document.createElement('div')
    //output.id = 'shapeDataOutput' + geometry.name + geometry.shapeType
    output.style.minWidth = '100%'
    output.style.height = '250px'
    output.style.background = '#333'
    output.style.border = '1px solid #fff4'
    output.style.overflowY = 'auto'
    output.style.wordWrap = 'break-word'
    output.style.color = '#888'
    output.style.fontSize = '10px'
    popup.appendChild(output)
    var copyButton = document.createElement('button')
    copyButton.style.border = 'none'
    copyButton.style.padding = '3px'
    copyButton.style.cursor = 'pointer'
    copyButton.fontSize = '20px'
    copyButton.style.borderRadius = '10px'
    copyButton.style.margin = '10px'
    copyButton.style.minWidth = '100px'
    copyButton.innerHTML = '📋 copy'
    copyButton.title = "copy shape data to clipboard"
    copyButton.onclick = () => {
      var range = document.createRange()
      range.selectNode(output)
      window.getSelection().removeAllRanges()
      window.getSelection().addRange(range)
      document.execCommand("copy")
      window.getSelection().removeAllRanges()
      copyButton.innerHTML = 'COPIED!'
      setTimeout(() => {
        copyButton.innerHTML = '📋 copy'
      } , 1000)
    }
    popup.appendChild(copyButton)
    var closeButton = document.createElement('button')
    closeButton.onclick = () => popup.remove()
    
    closeButton.style.border = 'none'
    closeButton.style.padding = '3px'
    closeButton.style.cursor = 'pointer'
    closeButton.fontSize = '20px'
    closeButton.style.borderRadius = '10px'
    closeButton.style.margin = '10px'
    closeButton.style.background = '#faa'
    closeButton.style.minWidth = '100px'
    closeButton.innerHTML = 'close'
    popup.appendChild(closeButton)
    
    var processedOutput = {
      vertices: [],
      normals: [],
      normalVecs: [],
      uvs: [],
    }
    vertices.map(v => processedOutput.vertices.push(Math.round(v*1e3) / 1e3))
    for(var i = 0; i < normals.length; i+=6){
      
      var X1 = normals[i+0]
      var Y1 = normals[i+1]
      var Z1 = normals[i+2]
      var X2 = flipNormals ? normals[i+0] - (normals[i+3] - normals[i+0]) : normals[i+3]
      var Y2 = flipNormals ? normals[i+1] - (normals[i+4] - normals[i+1]) : normals[i+4]
      var Z2 = flipNormals ? normals[i+2] - (normals[i+5] - normals[i+2]) : normals[i+5]
      X1 = Math.round(X1*1e3) / 1e3
      Y1 = Math.round(Y1*1e3) / 1e3
      Z1 = Math.round(Z1*1e3) / 1e3
      X2 = Math.round(X2*1e3) / 1e3
      Y2 = Math.round(Y2*1e3) / 1e3
      Z2 = Math.round(Z2*1e3) / 1e3
      processedOutput.normals.push(X1,Y1,Z1, X2,Y2,Z2)
    }
    for(var i = 0; i < normalVecs.length; i++){
      processedOutput.normalVecs.push((flipNormals ? -1 : 1) * Math.round(normalVecs[i]*1e3) / 1e3)
    }
    uvs.map(v => processedOutput.uvs.push(Math.round(v*1e3) / 1e3))
    output.innerHTML = JSON.stringify(processedOutput)
    document.body.appendChild(popup)
  }


  if(!resolvedFromCache){
    vertices   = new Float32Array(vertices)
    normals    = new Float32Array(normals)
    normalVecs = new Float32Array(normalVecs)
    uvs        = new Float32Array(uvs)
  }
  
  
  
  // link geometry buffers
  
  //vertics, indices
  vertex_buffer = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, vertex_buffer)
  gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ARRAY_BUFFER, null)
  vIndices = new Uint32Array( Array(vertices.length/3).fill().map((v,i)=>i) )
  Vertex_Index_Buffer = gl.createBuffer()
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, Vertex_Index_Buffer)
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, vIndices, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, null)

  //normals, indices
  normalVec_buffer = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, normalVec_buffer)
  gl.bufferData(gl.ARRAY_BUFFER, normalVecs, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ARRAY_BUFFER, null)
  nVecIndices = new Uint32Array( Array(normalVecs.length/3).fill().map((v,i)=>i) )
  NormalVec_Index_Buffer = gl.createBuffer()
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, NormalVec_Index_Buffer)
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, nVecIndices, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, null)
  
  //normal lines for drawing, indices
  normal_buffer = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, normal_buffer)
  gl.bufferData(gl.ARRAY_BUFFER, normals, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ARRAY_BUFFER, null)
  nIndices = new Uint32Array( Array(normals.length/3).fill().map((v,i)=>i) )
  Normal_Index_Buffer = gl.createBuffer()
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, Normal_Index_Buffer)
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, nIndices, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, null)

  //uvs, indices
  uv_buffer = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, uv_buffer)
  gl.bufferData(gl.ARRAY_BUFFER, uvs, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ARRAY_BUFFER, null)
  uvIndices = new Uint32Array( Array(uvs.length/2).fill().map((v,i)=>i) )
  UV_Index_Buffer = gl.createBuffer()
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, UV_Index_Buffer)
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, uvIndices, gl.STATIC_DRAW)
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, null)

  if(equirectangular == -1) equirectangular = false
  if(equirectangularHeightmap == -1) equirectangularHeightmap = false

  var updateGeometry = {
    x, y, z,
    roll, pitch, yaw, color, colorMix,
    size, subs, name, url, averageNormals,
    showNormals, shapeType, exportShape,
    sphereize, equirectangular, flipNormals,
    vertices, normals, normalVecs, uvs,
    vertex_buffer, Vertex_Index_Buffer,
    normal_buffer, Normal_Index_Buffer, muted,
    normalVec_buffer, NormalVec_Index_Buffer,
    nVecIndices, uv_buffer, UV_Index_Buffer,
    vIndices, nIndices, uvIndices, map, video,
    textureMode, isSprite, isLight, playbackSpeed,
    disableDepthTest, lum, alpha, involveCache,
    renderer, isParticle, penumbra, wireframe,
    canvasTexture, canvasTextureMix, showBounding,
    boundingColor, heightMap, heightMapIntensity,
    heightMapIsCanvas, equirectangularHeightmap,
    rotationMode
  }
  Object.keys(updateGeometry).forEach((key, idx) => {
    geometry[key] = updateGeometry[key]
  })
  
  
  if(shapeType == 'particles') {
    await renderer.particleShader.ConnectGeometry(geometry)
  }else{
    if(shapeType == 'point light' || shapeType == 'sprite'){
      if(typeof geoOptions.color == 'undefined'){
        geometry.color = 0xaaaaaa
      }
      if(shapeType == 'point light'){
        geometry.pointLightID = renderer.pointLights.length
        renderer.pointLights.push(geometry)
      }
    }
    await renderer.nullShader.ConnectGeometry(geometry)
  }
  
  return geometry
}

const GenericPopup = async (msg='', isPrompt=false, callback=()=>{},
                             width=400, height= 300) => {
  var popup = document.createElement('div')
  popup.className = 'genericPopup'
  popup.style.position = 'fixed'
  popup.style.zIndex = 100000
  popup.style.left = '50%'
  popup.style.top = '50%'
  popup.style.transform = 'translate(-50%, -50%)'
  popup.style.background = '#0008'
  popup.style.padding = '20px'
  popup.style.width = `${width}px`
  popup.style.height = `${height}px`
  popup.style.border = '1px solid #fff4'
  popup.style.borderRadius = '5px'
  popup.style.fontFamily = 'monospace'
  popup.style.fontSize = '20px'
  popup.style.color = '#fff'
  var titleEl = document.createElement('div')
  titleEl.style.fontSize = '24px'
  titleEl.style.color = '#0f8c'
  titleEl.innerHTML = msg
  popup.appendChild(titleEl)
  if(isPrompt){
    var OKButton = document.createElement('button')
    OKButton.onclick = () => {
      callback()
      popup.remove()
    }
    OKButton.style.border = 'none'
    OKButton.style.padding = '3px'
    OKButton.style.cursor = 'pointer'
    OKButton.fontSize = '20px'
    OKButton.style.borderRadius = '10px'
    OKButton.style.margin = '10px'
    OKButton.style.background = '#faa'
    OKButton.style.minWidth = '100px'
    OKButton.innerHTML = 'SURE!'
    popup.appendChild(OKButton)
  }
  var closeButton = document.createElement('button')
  closeButton.onclick = () => popup.remove()
  closeButton.style.border = 'none'
  closeButton.style.padding = '3px'
  closeButton.style.cursor = 'pointer'
  closeButton.fontSize = '20px'
  closeButton.style.borderRadius = '10px'
  closeButton.style.margin = '10px'
  closeButton.style.background = '#faa'
  closeButton.style.minWidth = '100px'
  closeButton.innerHTML = 'close'
  popup.appendChild(closeButton)
  document.body.appendChild(popup)
}

const ImageToPo2 = image => {
  let ret = image
  if ( !(IsPowerOf2(image.width) && IsPowerOf2(image.height)) ) {
    let tCan = document.createElement('canvas')
    let tCtx = tCan.getContext('2d', {imageSmoothingEnabled: true})
    let r = 8
    let tsize=0
    let mdif = 6e6
    let d, j
    let h = Math.hypot(image.width, image.height)
    for(let i = 0; i<16; i++){
      if((d=Math.abs(tsize-h)) < mdif){
        mdif = d
        tsize = r * 2**i
        j=i
      }
    }
    tsize -= r * 2**(j-1)
    tCan.width  = tsize
    tCan.height = tsize
    tCtx.drawImage(image, 0, 0, tCan.width, tCan.height)
    ret = new Image()
    //ret.src = tCan.toDataURL()
    ret = tCan
  }
  return ret
}

const VideoToImage = video => {
  if(typeof video != 'undefined'){
    
    let tgtWidth
    let tgtHeight
    
    if(scratchCanvas.width != video.videoWidth ||
       scratchCanvas.height != video.videoHeight){
       tgtWidth = video.videoWidth
       tgtHeight = video.videoHeight
    }else{
       tgtWidth = scratchCanvas.width
       tgtHeight= scratchCanvas.height
    }

    if ( !(IsPowerOf2(tgtWidth) && IsPowerOf2(tgtHeight)) ) {
      let r = 8
      let tsize=0
      let mdif = 6e6
      let d, j
      let h = Math.hypot(tgtWidth, tgtHeight)
      for(let i = 0; i<12; i++){
        if((d=Math.abs(tsize-h)) < mdif){
          mdif = d
          tsize = r * 2**i
          j=i
        }
      }
      tsize -= r * 2**(j-1)
      tsize = Math.min(512, tsize)
      tgtWidth = tsize / 1
      tgtHeight = tsize / 1
    }

    if(scratchCanvas.width != tgtWidth ||
         scratchCanvas.width != tgtHeight){
      scratchCanvas.width  = tgtWidth
      scratchCanvas.height = tgtHeight
    }
    sctx.drawImage(video, 0, 0, tgtWidth, tgtHeight)
  }else{
    scratchCanvas.width  = 1
    scratchCanvas.height = 1
  }
  return scratchCanvas
}

 
 
const BindImage = (gl, resource, binding, textureMode='image', tval=-1, url='', involveCache = true) => {
  let texImage
  switch(textureMode){
    case 'canvas': case 'heightImage':
      texImage = resource
    break
    case 'video':
      if(involveCache && (cacheItem=cache.texImages.filter(v=>v.url==url && tval != -1 && v.tVal == tval)).length){
        console.log('found video texture in cache... using it')
        texImage = cacheItem[0].texImage
      }else{
        texImage = VideoToImage(resource)
        if(tval == -1){
          cache.texImages.push({
            url,
            tval,
            texImage
          })
        }
      }
    break
    case 'image':
      if(involveCache && (cacheItem = cache.texImages.filter(v=>v.url==url)).length){
        console.log('found image texture in cache... using it')
        texImage = cacheItem[0].texImage
      }else{
        texImage = ImageToPo2(resource)
        if(tval == -1){
          cache.texImages.push({
            url,
            tval,
            texImage
          })
        }
      }
    break
    default:
    break
  }
  //gl.activeTexture(gl.TEXTURE0)
  gl.bindTexture(gl.TEXTURE_2D, binding)
  if(texImage.width && texImage.height){
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, texImage)
  }
  //gl.generateMipmap(gl.TEXTURE_2D)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.REPEAT);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.REPEAT);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
  
  //gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
  //gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
  //gl.activeTexture(gl.TEXTURE0)
}


const SyncNormals = (shape, averageNormals=false, flipNormals=false) => {
  var v = shape.vertices
  var normals = new Float32Array()
  var facet = new Float32Array()
  var X, Y, Z, facet, a
  for(var i = 0; i<v.length; i+=3){
    X = v[i+0]
    Y = v[i+1]
    Z = v[i+2]
    facet = [...facet, [X, Y, Z]]
    if(i%9 == 6){
      var n = Normal(facet)
      normals = [...normals, n, n, n]
      facet = new Float32Array()
    }
  }
  if(averageNormals){
    var avN = new Float32Array()
    for(var i=0; i< v.length; i+=3){
      var nx1 = v[i+0]
      var ny1 = v[i+1]
      var nz1 = v[i+2]
      var nidx1 = i/3|0
      var nx2 = normals[nidx1][3] - normals[nidx1][0]
      var ny2 = normals[nidx1][4] - normals[nidx1][1]
      var nz2 = normals[nidx1][5] - normals[nidx1][2]
      var ct = 0
      a = [0,0,0]
      shape.normalAssocs[nidx1].map(idx =>{
        var nidx = idx / 3 | 0
        a[0] += normals[nidx][3] - normals[nidx][0]
        a[1] += normals[nidx][4] - normals[nidx][1]
        a[2] += normals[nidx][5] - normals[nidx][2]
        ct ++
      })
      a[0] /= ct
      a[1] /= ct
      a[2] /= ct
      avN = [...avN, ...a]
      shape.normals[i*2+0] = nx1
      shape.normals[i*2+1] = ny1
      shape.normals[i*2+2] = nz1
      shape.normals[i*2+3] = nx1 + a[0] * (flipNormals ? -1 : 1)
      shape.normals[i*2+4] = ny1 + a[1] * (flipNormals ? -1 : 1)
      shape.normals[i*2+5] = nz1 + a[2] * (flipNormals ? -1 : 1)
    }
    var n = shape.normalVecs
    for(var i = 0; i<n.length; i++) n[i] = avN[i]
  }
}

const GetShaderCoord = (vx, vy, vz, geometry, renderer,
                        nx=0, ny=0, nz=0, uvx=0, uvy=0,
                        equirectangularPlugin=false, omitSplitCheck=true,
                        splitCheckPass=0) => {
  var X, Y, Z, ar
  if(geometry.heightMap && SHMdata.length){
    var uvi
    if(geometry.equirectangularHeightmap){
      var p, p1, p2
      var hvx = vx
      var hvy = vy
      var hvz = vz
      var dist = Math.hypot(hvx, hvy, hvz)
      p = Math.atan2(hvx, hvz)
      p1 = p / Math.PI / 2;
      p2 = Math.acos(hvy / (Math.hypot(hvx, hvy, hvz)+.00001)) / Math.PI;
      uvi = [p1, p2];
    } else {
      uvi = [uvx, uvy];
    }

    var idx     = (((scratchHeightMap.width * uvi[0]) | 0) + ((scratchHeightMap.height * scratchHeightMap.width * uvi[1]) | 0)) * 4
    var red     = SHMdata[idx+0] / 256
    var green   = SHMdata[idx+1] / 256
    var blue    = SHMdata[idx+2] / 256
    //var alpha = SHMdata[idx+3] / 256
    
    
    var lum = ((red + green + blue) / 3) * (geometry.heightMapIntensity) / 2
    vx += nx * lum
    vy += ny * lum
    vz += nz * lum
  }
  
  vy *= -1
  
  ar = R_ypr(vx, vy, vz, {
    roll:  -geometry.roll + .01,
    pitch: -geometry.pitch,
    yaw:   geometry.yaw,
  }, false)
  vx = ar[0]
  vy = ar[1]
  vz = ar[2]

  if(geometry.isLight){
    ar = R_rpy(vx, vy, vz, {
      roll:  renderer.roll,
      pitch: renderer.pitch,
      yaw:  -renderer.yaw,
    }, false)
    vx = ar[0]
    vy = ar[1]
    vz = ar[2]
  }

  var cpx = renderer.x
  var cpy = renderer.y
  var cpz = renderer.z

  vx += geometry.x
  vy -= geometry.y
  vz += geometry.z
  var posx, posy, posz
  if(renderer.cameraMode.toLowerCase() == 'fps'){
    vx += cpx
    vy -= cpy
    vz += cpz
    
    ar = R_ypr(vx, vy, vz, {
      roll: -renderer.roll,
      pitch: -renderer.pitch,
      yaw: renderer.yaw,
    }, false)
    vx = ar[0]
    vy = ar[1]
    vz = ar[2]

    cpx = 0
    cpy = 0
    cpz = 0
  }else{
    ar = R_ypr(vx, vy, vz, {
      roll: -renderer.roll,
      pitch: -renderer.pitch,
      yaw: renderer.yaw,
    }, false)
    vx = ar[0]
    vy = ar[1]
    vz = ar[2]
  }
  
  posx = vx
  posy = vy
  posz = vz
  
  var skip = false
  
  if(equirectangularPlugin){
    //posx += renderer.x
    //posy -= renderer.y
    X = posx + cpx
    Y = posy - cpy
    Z = posz + cpz
    var dist = Math.hypot(X, Y, Z)
    var p1, d
    if(!omitSplitCheck){
      if(splitCheckPass == 0){
        p = Math.atan2(X, Z) + Math.PI/2
        d = Math.hypot(X, Z)
        X = S(p) * d
        Z = C(p) * d
        p1 = ((Math.atan2(X, Z) / Math.PI + 2) % 2 ) - 1
        skip = p1 <= -1
        p1 += .5;
      }else{
        p = Math.atan2(X, Z) - Math.PI/2
        d = Math.hypot(X, Z)
        X = S(p) * d
        Z = C(p) * d
        p1 = ((Math.atan2(X, Z) / Math.PI + 2) % 2 ) - 1
        skip = p1 >= 1
        p1 -= .5;
      }
    }else{
      p1 = Math.atan2(X, Z) / Math.PI
    }
    
    var p2 = -(Math.acos(Y / (dist + .0001)) / Math.PI * 2.0 - 1.0)
    return skip ? false : [renderer.width  / 2 + p1 * renderer.width / 2,
                           renderer.height / 2 + p2 * renderer.height / 2,
                           dist]
  }else{
    vx += cpx
    vy -= cpy
    vz -= cpz
    
    var fov = renderer.fov
    var camz = cpz / 1e3 * Math.pow(5.0, Math.log(fov) / 1.609438) +cpz
    Z = vz + camz
    if(Z>0){
      X = renderer.width / 2 + vx / Z * fov / 2
      Y = renderer.height / 2 + vy / Z * fov / 2
      return [X, Y, Z]
    }
    return false
  }
}

const ShowBounding = (shape, renderer, draw=true,
                      equirectangularPlugin=-1,
                      omitSplitCheck=true, splitCheckPass=0) => {
                        
  if(equirectangularPlugin == -1){
    equirectangularPlugin = renderer.equirectangularPlugin
  }
  
  if(shape.isLight) return [] // aesthetic issue with raw rectangles, not critical

  var X, Y, Z
  
  var X1, Y1, X2, Y2, X3, Y3, X4, Y4
  var p, d, a, b, maxp, tidx, tpart, mind
  var memo=[]
  var pts = []
  const recurse = (ar, idx, oidx=-1, op=9) => {
    if(oidx == idx) return
    oidx = idx
    memo=[...memo, idx]
    
    X1 = ar[idx][0]
    Y1 = ar[idx][1]
    
    maxp = tidx = -9
    ar.map((v, i) =>{
      if(i!=idx){
        X2 = ar[i][0]
        Y2 = ar[i][1]
        if((p = Math.atan2(Y2-Y1-.00001, X1-X2)) >= maxp && p <= op){
          maxp = p
          tidx = i
        }
      }
    })
    
    if(tidx == -9) return
    if(draw) pts = [...pts, [...ar[tidx]]]
    recurse(ar, tidx, oidx, maxp)
  }

  var a     = [], b, p, ox=-1, oy=1e6, ax, ay, nx, ny, nz, uvx, uvy
  var sd    = 3 //shape.isParticle ? 1 : 3
  var dset  = shape.shader.datasets[shape.datasetIdx]

  if(shape.heightMap){
    if(shape.heightTextureMode == 'video') {
      scratchHeightMap.width  = dset.heightResource.videoWidth / 2
      scratchHeightMap.height = dset.heightResource.videoHeight / 2
    } else {
      scratchHeightMap.width  = dset.heightResource.width / 2
      scratchHeightMap.height = dset.heightResource.height / 2
    }
    SHMctx.drawImage(dset.heightResource, 0, 0, scratchHeightMap.width, scratchHeightMap.height)
    SHMdata = scratchHeightMap.width ? SHMctx.getImageData(0,0, scratchHeightMap.width, scratchHeightMap.height).data : []
  }
  
  for(var i=0; i<shape.vertices.length; i+=sd){
    if(shape.isParticle || !(i%9)){
      ax = ay = 0
      //Overlay.ctx.beginPath()
      b = []
    }
    X = shape.vertices[i+0]
    Y = shape.vertices[i+1]
    Z = shape.vertices[i+2]
    nx = i+0 < shape.normalVecs.length ? shape.normalVecs[i+0] : 0
    ny = i+1 < shape.normalVecs.length ? shape.normalVecs[i+1] : 0
    nz = i+2 < shape.normalVecs.length ? shape.normalVecs[i+2] : 0
    var uidx = (i/sd | 0) * 2
    uvx = uidx+0 < shape.uvs.length ? shape.uvs[uidx+0] : 0
    uvy = uidx+1 < shape.uvs.length ? shape.uvs[uidx+1] : 0
    var ar = GetShaderCoord(X, Y, Z, shape, renderer,
                            nx, ny, nz, uvx, uvy,
                            equirectangularPlugin,
                            omitSplitCheck, splitCheckPass)
    if(ar){
      b = [...b, [ar[0], ar[1]]]
      ax += ar[0]
      ay += ar[1]
      
      if(shape.isParticle || i%9 == 6){
        ax /= sd
        ay /= sd
        if(Math.hypot(ax-ox, ay-oy) > 10){
          ox = ax
          oy = ay
          a = [...a, b]
        }
      }
      //if(ar.length){
        //Overlay.ctx.lineTo(...ar)
        //if(false && i%9 == 6){  // show wireframe [disabled]
          //Overlay.ctx.closePath()
          //Overlay.ctx.lineWidth = 50 / (1 + Z)
          //Overlay.ctx.strokeStyle = '#f004'
          //Overlay.ctx.stroke()
          //Overlay.ctx.lineWidth =1
          //Overlay.ctx.strokeStyle = '#f00'
          //Overlay.ctx.stroke()
        //}
      //}
    }
  }
  
  if(shape.isParticle){
    var X1, Y1
    b = []
    a.map((triangle, idx) => {
      X1 = triangle[0][0]
      Y1 = triangle[0][1]
      if(!b.filter(v=>v[0]==X1&&v[1]==Y1).length) {
        b = [...b, [X1,Y1]]
      }
    })
  }else{
    var X1, Y1, X2, Y2, X3, Y3, X4, Y4
    b = []
    a.map((triangle, idx) => {
      if(triangle.length > 2){
        X1 = triangle[0][0]
        Y1 = triangle[0][1]
        X2 = triangle[1][0]
        Y2 = triangle[1][1]
        X3 = triangle[2][0]
        Y3 = triangle[2][1]
        if(!b.filter(v=>v[0]==X1&&v[1]==Y1).length) {
          b = [...b, [X1,Y1]]
        }
        if(!b.filter(v=>v[0]==X2&&v[1]==Y2).length) {
          b = [...b, [X2,Y2]]
        }
        if(!b.filter(v=>v[0]==X3&&v[1]==Y3).length) {
          b = [...b, [X3,Y3]]
        }
      }
    })
  }
  var miny = 6e6, midx = -1
  b.map((v, i) => {
    if(v[1] <= miny){
      midx = i
      miny = v[1]
    }
  })
  
  var l
  if(b.length){
    //Overlay.ctx.lineTo(...b[midx])
    recurse(b, midx)
    if(draw) {
      var rgb = RGBFromHex(shape.boundingColor)
      Overlay.ctx.strokeStyle = `rgba(${rgb[0]*256},${rgb[1]*256},${rgb[2]*256},.5)`
      Overlay.ctx.globalAlpha = 1
      Overlay.ctx.beginPath()
      pts.map((v, i) => {
        Overlay.ctx.lineWidth = 10
        var lx1 = pts[i][0]
        var ly1 = pts[i][1]
        var lx2 = pts[l=(i+1)%pts.length][0]
        var ly2 = pts[l][1]
        if(omitSplitCheck){
          Overlay.ctx.moveTo(lx1, ly1)
          Overlay.ctx.lineTo(lx2, ly2)
        }else{
          if(splitCheckPass == 0){
            if(lx1 >= Overlay.width/2 - Overlay.width/8 &&
               lx1 < Overlay.width + Overlay.width/8 &&
               lx2 >= Overlay.width/2 - Overlay.width/8 &&
               lx2 < Overlay.width + Overlay.width/8){
              Overlay.ctx.moveTo(lx1, ly1)
              Overlay.ctx.lineTo(lx2, ly2)
             }
          }else{
            if(lx1 <= Overlay.width/2 + Overlay.width/8 &&
               lx1 > -Overlay.width/8 &&
               lx2 <= Overlay.width/2 + Overlay.width/8 &&
               lx2 > -Overlay.width/8){
              Overlay.ctx.moveTo(lx1, ly1)
              Overlay.ctx.lineTo(lx2, ly2)
             }
          }
        }
      })
      Overlay.ctx.stroke()
    }
  }
  
  return memo.map(idx => [
    b[idx][0], b[idx][1]
  ])
}

const AverageNormals = (verts, normals, shapeType) => {
  normals.length = 0
  var isPolyhedron = IsPolyhedron(shapeType)
  // expects triangles
  var n
  for(var i = 0; i<verts.length; i+=3){
    if(!(i%9)){
      n = Normal([
        [verts[i+0],verts[i+1],verts[i+2]],
        [verts[i+3],verts[i+4],verts[i+5]],
        [verts[i+6],verts[i+7],verts[i+8]]
      ], isPolyhedron)
    }
    normals[i*2+0] = verts[i+0]
    normals[i*2+1] = verts[i+1]
    normals[i*2+2] = verts[i+2]
    normals[i*2+3] = verts[i+0] + (n[0] - n[3])
    normals[i*2+4] = verts[i+1] + (n[1] - n[4])
    normals[i*2+5] = verts[i+2] + (n[2] - n[5])
  }
  
  var ret = new Float32Array()
  var modSrc = structuredClone(normals)
  var a, ct, ax, ay, az
  var X1a, Y1a, Z1a, X2a, Y2a, Z2a
  var X1b, Y1b, Z1b, X2b, Y2b, Z2b
  for(var i=0; i<normals.length; i+=6){
    X1a = normals[i+0]
    Y1a = normals[i+1]
    Z1a = normals[i+2]
    X2a = normals[i+3]
    Y2a = normals[i+4]
    Z2a = normals[i+5]
    ax = X2a
    ay = Y2a
    az = Z2a
    ct = 1
    for(var j=0; j<normals.length; j+=6){
      if(j!=i){
        X1b = normals[j+0]
        Y1b = normals[j+1]
        Z1b = normals[j+2]
        X2b = normals[j+3]
        Y2b = normals[j+4]
        Z2b = normals[j+5]
        if(Math.hypot(X1a - X1b, Y1a - Y1b, Z1a - Z1b) < .01){
          ax += X2b
          ay += Y2b
          az += Z2b
          ct++
        }
      }
    }
    modSrc[i+3] = ax /= ct
    modSrc[i+4] = ay /= ct
    modSrc[i+5] = az /= ct
  }
  modSrc.map((v,i)=>normals[i]=v)
}

const BasicShader = async (renderer, options=[]) => {
  
  const gl = renderer.gl
  var program
  
  var dataset = {
    iURL: null,
    locT: null,
    locUv: null,
    locFov: null,
    program: null,
    heightMapURL: null,
    optionalUniforms: [],
    optionalLighting: [],
  }
  
  options.map(option => {
    Object.keys(option).forEach((key, idx) => {
      switch(key.toLowerCase()){
        case 'lighting':
          switch(option[key].type.toLowerCase()){
            case 'ambientlight': 
              if(typeof option[key]?.enabled == 'undefined' ||
                 !!option[key].enabled){
                var lightingOption = {
                  name: option[key].type,
                  value: typeof option[key].value == 'undefined' ?
                          renderer.ambientLight : option[key].value,
                }
                dataset.optionalLighting.push( lightingOption )
              }
            break
            default:
            break
          }
        break
        case 'uniform':
          switch(option[key].type.toLowerCase()){
            case 'reflection':
              if(typeof option[key]?.enabled == 'undefined' ||
                 !!option[key].enabled){
                var uniformOption = {
                  name:                option[key].type,
                  playbackSpeed:       typeof option[key].playbackSpeed == 'undefined' ?
                                         1 : option[key].playbackSpeed,
                  involveCache:        typeof option[key].involveCache == 'undefined' ?
                                         true : option[key].involveCache,
                  muted:               typeof option[key].muted == 'undefined' ?
                                         true : option[key].muted,
                  map:                 option[key].map,
                  loc:                 'locReflection',
                  value:               typeof option[key].value == 'undefined' ?
                                         .5 : option[key].value,
                  flatShading:         typeof option[key].flatShading == 'undefined' ?
                                         false : option[key].flatShading,
                  flipReflections:     typeof option[key].flipReflections == 'undefined' ?
                                         false : option[key].flipReflections,
                  flatShadingUniform:  'refFlatShading',
                  dataType:            'uniform1f',
                  vertDeclaration:     `
                  `,
                  vertCode:            `
                  `,
                  fragDeclaration:     `
                    uniform float reflection;
                    uniform float refFlatShading;
                    uniform float refOmitEquirectangular;
                    uniform float refFlipRefs;
                    uniform sampler2D reflectionMap;
                  `,
                  fragCode:            `
                    //light.rgb *= .5;
                    //light.rgb += .05;
                    float refP1, refP2;
                    if(refOmitEquirectangular != 1.0){
                      vec3 reflectionPos = R_ypr(nVi, geoOri);
                      float px = reflectionPos.x;
                      float py = reflectionPos.y;
                      float pz = reflectionPos.z;
                      refP1 = -atan(px, pz) / M_PI / 4.0;
                      refP2 = acos( py / (.001 + sqrt(px * px + py * py + pz * pz))) / M_PI;
                      if(refFlipRefs != 0.0) refP2 = 1.0 - refP2;
                    } else {
                      refP1 = vUv.x;
                      refP2 = vUv.y;
                    }
                    
                    vec2 refCoords = vec2(1.0 - refP1 * 2.0, refP2);
                    vec4 refCol = vec4(texture2D(reflectionMap, vec2(refCoords.x, refCoords.y)).rgb * 1.25, reflection / 1.0);
                    mixColor = merge(mixColor, refCol);
                    baseColorIp = 1.0 - reflection;
                    //light += reflection / 4.0;
                  `,
                }
                dataset.optionalUniforms.push( uniformOption )
              }
            break
            case 'phong':
              if(typeof option[key]?.enabled == 'undefined' ||
                 !!option[key].enabled){
                var uniformOption = {
                  name:                option[key].type,
                  loc:                 'locPhong',
                  value:               typeof option[key].value == 'undefined' ?
                                         .3 : option[key].value,
                  flatShading:         typeof option[key].flatShading == 'undefined' ?
                                         false : option[key].flatShading,
                  flatShadingUniform:  'phongFlatShading',
                  theta:                typeof option[key].theta == 'undefined' ?
                                          .5: option[key].theta,
                  dataType:            'uniform1f',
                  vertDeclaration:     `
                  `,
                  vertCode:            `
                    hasPhong = 1.0;
                  `,
                  fragDeclaration:     `
                    uniform float phong;
                    uniform float phongTheta;
                    uniform float phongFlatShading;
                  `,
                  fragCode:            `
                    if(isLight == 0.0 &&
                       isSprite == 0.0 && isParticle == 0.0){
                      //light.rgb *= .5;
                      float phongP1, phongP2;
                      float px, py, pz;
                      if(phongFlatShading != 0.0){
                        px = 0.0;
                        py = 0.0;
                        pz = 0.0;
                      }else{
                        px = nV.x;
                        py = nV.y;
                        pz = nV.z;
                      }
                      phongP1 = atan(px, pz) + phongTheta;
                      phongP2 = -acos( py / (.001 + sqrt(px * px + py * py + pz * pz)));

                      float fact = pow(pow((1.0+cos(phongP1)) * (1.0+cos(phongP2+M_PI/2.0-.2)), 3.0), 3.0) / 5e5 * phong ;
                      light = vec4(light.rgb + fact, 1.0) * 25.0;
                    }
                  `,
                }
                dataset.optionalUniforms.push( uniformOption )
              }
            break
          }
        break
      }
    })
  })
  
  let ret = {
    ConnectGeometry: null,
    datasets: [],
  }
  
  
  if(renderer.contextType != '2d') {
    gl.enable(gl.DEPTH_TEST)
    //gl.clear(gl.COLOR_BUFFER_BIT)
    //gl.disable(gl.CULL_FACE)
    gl.cullFace(gl.BACK)
    if(renderer.alpha) {
      gl.blendFunc(gl.SRC_ALPHA, gl.ONE)
      gl.enable(gl.BLEND)
      gl.disable(gl.DEPTH_TEST)
    }else{
      //gl.cullFace(gl.BACK)
    }
  
    let uVertDeclaration = ''
    dataset.optionalUniforms.map(v=>{ uVertDeclaration += ("\n" + v.vertDeclaration + "\n") })
    let uVertCode= ''
    dataset.optionalUniforms.map(v=>{ uVertCode += ("\n" + v.vertCode + "\n") })

    let uFragDeclaration = ''
    dataset.optionalUniforms.map(v=>{ uFragDeclaration += ("\n" + v.fragDeclaration + "\n") })
    let uFragCode= ''
    dataset.optionalUniforms.map(v=>{ uFragCode += ("\n" + v.fragCode + "\n") })

    ret.vert = `
      precision highp float;
      #define M_PI 3.14159265358979323
      attribute vec2 uv;
      ${uVertDeclaration}
      
      uniform float t;
      uniform vec3 color;
      uniform float flatShading;
      uniform float ambientLight;
      uniform float plugin;
      uniform vec3 camPos;
      uniform vec3 camOri;
      uniform vec3 geoPos;
      uniform vec3 geoOri;
      uniform int rotationMode;
      uniform float omitSplitCheck;
      uniform float splitCheckPass;
      uniform float pointSize;
      uniform float isSprite;
      uniform float isLight;
      uniform float cameraMode;
      uniform float isParticle;
      uniform float penumbraPass;
      uniform float fov;
      uniform float equirectangular;
      uniform float equirectangularHeightmap;
      uniform float renderNormals;
      uniform vec2 resolution;
      uniform float useHeightMap;
      uniform float heightMapIntensity;
      uniform sampler2D heightMap;
      attribute vec3 position;
      attribute vec3 normal;
      attribute vec3 normalVec;
      varying vec2 vUv;
      varying vec2 uvi;
      varying vec3 nVec;
      varying vec3 nVeci;
      varying vec3 fPos;
      varying vec3 fPosi;
      varying float skip;
      varying float hasPhong;
      
      
      vec3 R(vec3 pos, vec3 rot, int isGeo){
        float p, d;
        if(rotationMode == 0 || isGeo == 0) {
          pos.x = sin(p=atan(pos.x,pos.z)+rot.z)*(d=sqrt(pos.x*pos.x+pos.z*pos.z));
          pos.z = cos(p)*d;
          pos.y = sin(p=atan(pos.y,pos.z)+rot.y)*(d=sqrt(pos.y*pos.y+pos.z*pos.z));
          pos.z = cos(p)*d;
          pos.x = sin(p=atan(pos.x,pos.y)+rot.x)*(d=sqrt(pos.x*pos.x+pos.y*pos.y));
          pos.y = cos(p)*d;
        }
        if(rotationMode == 1 && isGeo == 1) {
          pos.y = sin(p=atan(pos.y,pos.z)+rot.y)*(d=sqrt(pos.y*pos.y+pos.z*pos.z));
          pos.z = cos(p)*d;
          pos.x = sin(p=atan(pos.x,pos.z)+rot.z)*(d=sqrt(pos.x*pos.x+pos.z*pos.z));
          pos.z = cos(p)*d;
          pos.x = sin(p=atan(pos.x,pos.y)+rot.x)*(d=sqrt(pos.x*pos.x+pos.y*pos.y));
          pos.y = cos(p)*d;
        }
        if(rotationMode == 2 && isGeo == 1) {
          pos.x = sin(p=atan(pos.x,pos.y)+rot.x)*(d=sqrt(pos.x*pos.x+pos.y*pos.y));
          pos.y = cos(p)*d;
          pos.y = sin(p=atan(pos.y,pos.z)+rot.y)*(d=sqrt(pos.y*pos.y+pos.z*pos.z));
          pos.z = cos(p)*d;
          pos.x = sin(p=atan(pos.x,pos.z)+rot.z)*(d=sqrt(pos.x*pos.x+pos.z*pos.z));
          pos.z = cos(p)*d;
        }
        return pos;
      }
      
      void main(){
        
        hasPhong = 0.0;
        
        float cx, cy, cz;
        
        if(renderNormals == 1.0){
          cx = normal.x;
          cy = normal.y;
          cz = normal.z;
        }else{
          cx = position.x;
          cy = position.y;
          cz = position.z;
        }
        
        
        if(useHeightMap != 0.0 && renderNormals == 0.0){
          nVeci = normalVec;
          vec4 h;
          float lum;

          if(equirectangularHeightmap != 0.0){
            float p;
            float p2;
            vec3 cpos = vec3(cx, cy, cz);
            p = flatShading == 1.0 ? atan(nVeci.x, nVeci.z): atan(cpos.x, cpos.z);
            float p1;
            p1 = p / M_PI / 2.0;
            p2 = flatShading == 1.0 ?
                  acos(nVeci.y / (sqrt(nVeci.x*nVeci.x + nVeci.y*nVeci.y + nVeci.z*nVeci.z)+.00001)) / M_PI   :
                  acos(cpos.y / (sqrt(cpos.x*cpos.x + cpos.y*cpos.y + cpos.z*cpos.z)+.00001)) / M_PI;
            uvi = vec2(p1, p2);
          } else {
            uvi = uv;
          }

            
          h = texture2D( heightMap, uvi);
          lum = ((h.r + h.g + h.b) / 3.0) * (heightMapIntensity) / 2.0;
          cx += normalVec.x * lum;
          cy += normalVec.y * lum;
          cz += normalVec.z * lum;

          
        }else{
          uvi = uv / 2.0;
          uvi = vec2(uvi.x, .5 - uvi.y);
          nVeci = normalVec;
        }
        
        fPosi = vec3(cx, cy, cz);
        
        // camera rotation
        
        vec3 geo, pos;
        float cpx = camPos.x;
        float cpy = camPos.y;
        float cpz = camPos.z;
        
        if(cameraMode == 1.0){  // 'FPS' mode
          if(isSprite != 0.0 || isLight != 0.0){
            geo = R(geoPos, camOri, 0);
            pos = R(vec3(cx, cy, cz),
                     vec3(0.0, -camOri.y + M_PI, 0.0), 0);
            pos = R(vec3(pos.x, pos.y, pos.z),
                     vec3(-camOri.x, 0.0, -camOri.z ), 0);
            pos = R(pos, geoOri, 1);
            //pos.x += cpx;
            //pos.y += cpy;
            //pos.z += cpz;
            pos = R(pos, camOri, 0);
            nVec = vec3(nVeci.x, nVeci.y, nVeci.z);
            nVec = R(nVec, geoOri, 1);
            nVec = R(nVec, vec3(0.0, camOri.y, camOri.z), 0);
          }else{
            geo = R(geoPos, camOri, 0);
            pos = R(vec3(cx, cy, cz), geoOri, 1);
            pos.x += cpx;
            pos.y += cpy;
            pos.z += cpz;
            pos = R(pos, camOri, 0);
            nVec = vec3(nVeci.x, nVeci.y, nVeci.z);
            nVec = R(nVec, geoOri, 1);
            nVec = R(nVec, vec3(0.0, camOri.y, camOri.z), 0);
          }
          cpx = 0.0;
          cpy = 0.0;
          cpz = 0.0;
          fPos = pos;
        }else{
          if(isSprite != 0.0 || isLight != 0.0){
            geo = R(geoPos, camOri, 0);
            pos = R(vec3(cx, cy, cz),
                     vec3(0.0, -camOri.y + M_PI, 0.0), 0);
            pos = R(pos,
                     vec3(-camOri.x, 0.0, -camOri.z ), 0);
            pos = R(pos, camOri, 0);
            nVec = vec3(nVeci.x, nVeci.y, nVeci.z);
            nVec = R(nVec, geoOri, 1);
            nVec = R(nVec, vec3(0.0, camOri.y, camOri.z), 0);
          }else{
            geo = R(geoPos, camOri, 0);
            pos = R(vec3(cx, cy, cz), geoOri, 1);
            pos = R(pos, camOri, 0);
            nVec = vec3(nVeci.x, nVeci.y, nVeci.z);
            nVec = R(nVec, geoOri, 1);
            nVec = R(nVec, vec3(0.0, camOri.y, camOri.z), 0);
          }
          fPos = pos;
        }
        
        ${uVertCode}
        
        float camz = cpz / 1e3 * pow(5.0, log(fov) / 1.609438);
        
        float X, Y;
        float Z = pos.z + camz + geo.z;
        if(isParticle != 0.0 && penumbraPass != 0.0) Z += .001;
        
        if( plugin ==  1.0 ){   // equirectangular post-processing plugin
          X = pos.x + cpx + geo.x;
          Y = pos.y + cpy + geo.y;
          Z = pos.z + cpz + geo.z;
          float dist = sqrt(X*X + Y*Y + Z*Z);
          float p1;
          if(omitSplitCheck == 0.0){
            if(splitCheckPass == 0.0){
              vec3 rot = R(vec3(X, Y, Z), vec3(0,0,M_PI/2.0), 0);
              X = rot.x;
              Y = rot.y;
              Z = rot.z;
              p1 = mod(atan(X, Z) / M_PI + 2.0, 2.0) - 1.0;
              skip = p1 <= -0.75 ? 1.0 : 0.0;
              p1 += .5;
            }else{
              vec3 rot = R(vec3(X, Y, Z), vec3(0,0,-M_PI/2.0), 0);
              X = rot.x;
              Y = rot.y;
              Z = rot.z;
              p1 = mod(atan(X, Z) / M_PI + 2.0, 2.0) - 1.0;
              skip = p1 >= 0.75 ? 1.0 : 0.0;
              p1 -= .5;
            }
          }else{
            skip = 0.0;
            p1 = atan(X, Z) / M_PI;
          }
          if(skip == 0.0){
            float p2 = - (acos(Y / (dist + .0001)) / M_PI * 2.0 - 1.0) * 1.05;
            gl_PointSize = 100.0 * pointSize / dist;
            gl_Position = vec4(p1, p2, dist/10000.0, 1.0);
            vUv = uv;
          }
        } else {  // default projection
          X = (pos.x + cpx + geo.x) / Z / resolution.x * fov;
          Y = (pos.y + cpy + geo.y) / Z / resolution.y * fov;
          if(Z > 0.0) {
            gl_PointSize = 100.0 * pointSize / Z;
            gl_Position = vec4(X, Y, Z/10000.0, 1.0);
            skip = 0.0;
            vUv = uv;
          }else{
            skip = 1.0;
          }
        }
      }
    `
    
    ret.frag = `
      precision highp float;
      #define M_PI 3.14159265358979323
      ${uFragDeclaration}
      uniform float t;
      uniform vec2 resolution;
      uniform float plugin;
      uniform float flatShading;
      uniform float isSprite;
      uniform float isLight;
      uniform float isParticle;
      uniform float cameraMode;
      uniform vec4 pointLightPos[16];
      uniform vec4 pointLightCol[16];
      uniform int pointLightCount;
      uniform float ambientLight;
      uniform float renderNormals;
      uniform float equirectangular;
      uniform float equirectangularHeightmap;
      uniform float colorMix;
      //uniform float penumbraPass;
      uniform vec3 color;
      uniform float useHeightMap;
      uniform float heightMapIntensity;
      uniform sampler2D heightMap;
      uniform sampler2D baseTexture;
      uniform sampler2D supplementalTexture;
      uniform float supplementalTextureMix;
      uniform float alpha;
      uniform vec3 camPos;
      uniform vec3 camOri;
      uniform vec3 geoPos;
      uniform vec3 geoOri;
      varying vec2 vUv;
      varying vec2 uvi;
      varying vec3 nVec;
      varying vec3 nVeci;
      varying vec3 fPos;
      varying vec3 fPosi;
      varying float skip;
      varying float hasPhong;

      vec4 merge (vec4 col1, vec4 col2){
        return vec4((col1.rgb * col1.a) + (col2.rgb * col2.a), 1.0);
      }
      
      vec2 Coords(float flatShading, vec3 nV) {
        vec2 ret;
        if(equirectangular == 1.0){
          float p;
          float p2;
          vec3 cpos = fPosi;
          p = flatShading == 1.0 ? atan(nV.x, nV.z): atan(cpos.x, cpos.z) * 1.0;
          float p1;
          p1 = p / M_PI / 2.0;
          p2 = flatShading == 1.0 ?
                acos(nV.y / (sqrt(nV.x*nV.x + nV.y*nV.y + nV.z*nV.z)+.00001)) / M_PI   :
                p2 = acos(cpos.y / (sqrt(cpos.x*cpos.x + cpos.y*cpos.y + cpos.z*cpos.z)+.00001)) / M_PI;
          ret = vec2(p1, p2);
        }else{
          ret = vUv;
        }
        return ret;
      }

      vec3 R_ypr(vec3 pos, vec3 rot){
        float p, d;
        pos.x = sin(p=atan(pos.x,pos.z)+rot.z)*(d=sqrt(pos.x*pos.x+pos.z*pos.z));
        pos.z = cos(p)*d;
        pos.y = sin(p=atan(pos.y,pos.z)+rot.y)*(d=sqrt(pos.y*pos.y+pos.z*pos.z));
        pos.z = cos(p)*d;
        pos.x = sin(p=atan(pos.x,pos.y)+rot.x)*(d=sqrt(pos.x*pos.x+pos.y*pos.y));
        pos.y = cos(p)*d;
        return pos;
      }
      
      vec3 R_yrp(vec3 pos, vec3 rot){
        float p, d;
        pos.x = sin(p=atan(pos.x,pos.z)+rot.z)*(d=sqrt(pos.x*pos.x+pos.z*pos.z));
        pos.z = cos(p)*d;
        pos.x = sin(p=atan(pos.x,pos.y)+rot.x)*(d=sqrt(pos.x*pos.x+pos.y*pos.y));
        pos.y = cos(p)*d;
        pos.y = sin(p=atan(pos.y,pos.z)+rot.y)*(d=sqrt(pos.y*pos.y+pos.z*pos.z));
        pos.z = cos(p)*d;
        return pos;
      }
      
      vec4 GetPointLight(){
        
        float ret = 0.0;
        vec4 rgba = vec4(0.0, 0.0, 0.0, 1.0);
        for(int i=0; i < 16; i++){
          //if(i >= pointLightCount) break;
          vec3 lpos = pointLightPos[i].xyz;
          lpos.x -= geoPos.x; //- camPos.x;
          lpos.y -= geoPos.y; //- camPos.y;
          lpos.z -= geoPos.z; //- camPos.z;
          lpos = R_yrp(lpos, vec3(camOri.x, camOri.y, camOri.z ));

          float mag = pointLightPos[i].w;
          ret = mag / (1.0 + pow(1.0 + sqrt((lpos.x-fPos.x) * (lpos.x-fPos.x) + (lpos.y-fPos.y) * (lpos.y-fPos.y) +
          + (lpos.z-fPos.z) * (lpos.z-fPos.z)), 2.0) / 3.0) * 40.0;
          
          rgba.r += ret * pointLightCol[i].r;
          rgba.g += ret * pointLightCol[i].g;
          rgba.b += ret * pointLightCol[i].b;
        }
        
        return pointLightCount > 0 ? vec4(rgba.rgb + ambientLight, 1.0) : vec4(ambientLight, ambientLight, ambientLight, 1.0);
      }

      void main() {
        if(isParticle != 0.0){
          gl_FragColor = merge(gl_FragColor, vec4(color.rgb, alpha));
        }else{
          float mixColorIp = colorMix;
          float baseColorIp = 1.0 - mixColorIp;
          vec4 mixColor = vec4(color.rgb, mixColorIp);
          vec4 light = hasPhong == 1.0 ? GetPointLight() :
                vec4(ambientLight, ambientLight, ambientLight, 1.0);
          float colorMag = 1.0;
          if(skip != 1.0){
            if(renderNormals == 1.0){
              gl_FragColor = vec4(1.0, 0.0, 0.0, 1.0);
            }else{
              float p;
              vec3 nV, nVi;
              if(useHeightMap != 0.0){
                
                float rad = .02;
                float rad2 = -.25;
                vec2 cr1, cr2, cr3;
                float lum1, lum2, lum3;
                for(float j=0.0; j<3.0; j+=1.0){
                  
                  float tx, ty;
                  p = M_PI * 2.0 / 3.0 * j;
                  tx = -sin(p) * rad;
                  ty = -cos(p) * rad;
                
                  vec2 hcoords;
                  if(equirectangularHeightmap == 1.0){
                    float p;
                    float p2;
                    vec3 cpos = fPosi;
                    p = flatShading == 1.0 ? atan(nV.x, nV.z): atan(cpos.x, cpos.z) * 1.0;
                    float p1;
                    p1 = p / M_PI / 2.0;
                    p2 = flatShading == 1.0 ?
                          acos(nV.y / (sqrt(nV.x*nV.x + nV.y*nV.y + nV.z*nV.z)+.00001)) / M_PI   :
                          p2 = acos(cpos.y / (sqrt(cpos.x*cpos.x + cpos.y*cpos.y + cpos.z*cpos.z)+.00001)) / M_PI;
                    tx += p1;
                    ty += p2;
                    hcoords = vec2(tx, ty);
                  }else{
                    hcoords = vUv + vec2(tx, ty);
                  }
                  
                  vec4 htexel = texture2D( heightMap, hcoords);
                  float lum = (htexel.r + htexel.g + htexel.b) / 3.0;
                  if(j == 0.0) {
                    lum1 = lum;
                    cr1 = hcoords;
                  }
                  if(j == 1.0) {
                    lum2 = lum;
                    cr1 = hcoords;
                  }
                  if(j == 2.0) {
                    lum3 = lum;
                    cr2 = hcoords;
                  }
                }

                float a1 = cr1.x;
                float a2 = cr1.y;
                float a3 = (lum1 - 0.5) * rad2;
                
                float b1 = cr2.x - a1;
                float b2 = cr2.y - a2;
                float b3 = (lum2 - 0.5) * rad2 - a3;
                float c1 = cr3.x - cr2.x;
                float c2 = cr3.y - cr2.y;
                float c3 = (lum3 - 0.5) * rad2 - (lum2 - 0.5) * rad2;
                vec3 anorm =  vec3(b2*c3-b3*c2, b3*c1-b1*c3, b1*c2-b2*c1) * (1.0 + heightMapIntensity) / 2.0;
                nV = normalize( nVec + anorm);
                nVi = normalize( nVeci + anorm);
              }else{
                nV = nVec;
                nVi = nVeci;
              }
              
              
              ${uFragCode}
              vec2 coords = Coords(0.0, nVi);
              vec4 texel = texture2D( baseTexture, coords);
              texel = merge(texel, vec4(texture2D( supplementalTexture, coords).rgb, supplementalTextureMix));
              if(isSprite != 0.0 || isLight != 0.0){
                gl_FragColor = vec4(texel.rgb * 2.0, texel.a * alpha);
              }else{
                texel.a = baseColorIp;
                vec4 col = merge(mixColor, texel);
                col.rgb *= light.rgb;
                gl_FragColor = vec4(col.rgb * colorMag, 1.0);
              }
            }
          }
        }
      }
    `
    
    const vertexShader = gl.createShader(gl.VERTEX_SHADER)
    gl.shaderSource(vertexShader, ret.vert)
    gl.compileShader(vertexShader)

    const fragmentShader = gl.createShader(gl.FRAGMENT_SHADER)
    gl.shaderSource(fragmentShader, ret.frag)
    gl.compileShader(fragmentShader)

    ret.ConnectGeometry = async (geometry, fromNullShader = false) => {
      
      if(0&&(geometry.shapeType == 'point light' || geometry.shapeType == 'sprite') &&
         typeof geometry?.shader != 'undefined') return
         
      var involveCache = geometry.involveCache

      var dset = structuredClone(dataset)
      ret.datasets = [...ret.datasets, dset]
      
      dset.program = gl.createProgram()
      
      gl.attachShader(dset.program, vertexShader)
      gl.attachShader(dset.program, fragmentShader)
      gl.linkProgram(dset.program)

      geometry.shader = ret
      var textureURL = geometry.map
      var heightMapURL = geometry.heightMap
      geometry.datasetIdx = ret.datasets.length - 1

      //gl.detachShader(dset.program, vertexShader)
      //gl.detachShader(dset.program, fragmentShader)
      //gl.deleteShader(vertexShader)
      //gl.deleteShader(fragmentShader)
      
                                
      if (gl.getProgramParameter(dset.program, gl.LINK_STATUS)) {
          
        gl.useProgram(dset.program)
        
        gl.bindBuffer(gl.ARRAY_BUFFER, geometry.vertex_buffer)
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, geometry.Vertex_Index_Buffer)
        dset.locPosition = gl.getAttribLocation(dset.program, "position")
        gl.vertexAttribPointer(dset.locPosition, 3, gl.FLOAT, false, 0, 0)
        gl.enableVertexAttribArray(dset.locPosition)

        gl.bindBuffer(gl.ARRAY_BUFFER, geometry.uv_buffer)
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, geometry.UV_Index_Buffer)
        dset.locUv= gl.getAttribLocation(dset.program, "uv")
        gl.vertexAttribPointer(dset.locUv , 2, gl.FLOAT, false, 0, 0)
        gl.enableVertexAttribArray(dset.locUv)

        gl.bindBuffer(gl.ARRAY_BUFFER, geometry.normal_buffer)
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, geometry.Normal_Index_Buffer)
        dset.locNormal = gl.getAttribLocation(dset.program, "normal")
        gl.vertexAttribPointer(dset.locNormal, 3, gl.FLOAT, true, 0, 0)
        gl.enableVertexAttribArray(dset.locNormal)
        
        gl.bindBuffer(gl.ARRAY_BUFFER, geometry.normalVec_buffer)
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, geometry.NormalVec_Index_Buffer)
        dset.locNormalVec = gl.getAttribLocation(dset.program, "normalVec")
        gl.vertexAttribPointer(dset.locNormalVec, 3, gl.FLOAT, true, 0, 0)
        gl.enableVertexAttribArray(dset.locNormalVec)
        if(!fromNullShader){
          if(!geometry.isLight){
            dset.optionalUniforms.map(async (uniform) => {
              switch(uniform.name){
                case 'reflection':
                  var url = uniform.map
                  if(url){
                    let l
                    let suffix = (l=url.split('.'))[l.length-1].toLowerCase()
                    uniform.refTexture = gl.createTexture()
                    switch(suffix){
                      case 'mp4': case 'webm': case 'avi': case 'mkv': case 'ogv':
                        uniform.textureMode = 'video'
                        if(involveCache && (cacheItem=cache.textures.filter(v=>v.url==url)).length){
                          console.log('found video in cache... using it')
                          uniform.video = cacheItem[0].resource
                          //uniform.video.playbackRate = uniform.video.defaultPlaybackRate = uniform.playbackSpeed
                          ret.datasets = [...ret.datasets, {texture: cacheItem[0].texture, iURL: url }]
                          //gl.activeTexture(gl.TEXTURE1)
                          //BindImage(gl, uniform.video, uniform.refTexture, uniform.textureMode, -1, url)
                        }else{
                          uniform.video = document.createElement('video')
                          uniform.video.muted = true
                          uniform.video.playbackRate = uniform.playbackSpeed
                          uniform.video.defaultPlaybackRate = uniform.playbackSpeed
                          ret.datasets = [...ret.datasets, {
                            texture: uniform.refTexture, iURL: url }]
                          uniform.video.loop = true
                          if(!uniform.muted && !audioConsent) {
                            audioConsent = true
                            GenericPopup('play audio OK?', true, ()=>{
                              cache.textures.filter(v=>v.url == url)[0].resource.muted = false
                              cache.textures.filter(v=>v.url == url)[0].resource.currentTime = 0
                              //cache.textures.filter(v=>v.url == url)[0].resource.playbackRate = cache.textures.filter(v=>v.url == url)[0].resource.defaultPlaybackRate = uniform.playbackSpeed
                              cache.textures.filter(v=>v.url == url)[0].resource.play()
                            })
                          }
                          uniform.video.playbackRate = uniform.video.defaultPlaybackRate = uniform.playbackSpeed
                          uniform.video.oncanplay = async () => {
                            uniform.video.play()
                          }
                          gl.activeTexture(gl.TEXTURE1)
                          BindImage(gl, uniform.video, uniform.refTexture, uniform.textureMode, -1, url)
                          cache.textures.push({
                            url,
                            resource: uniform.video,
                            texture: uniform.refTexture
                          })
                          await fetch(url).then(res=>res.blob()).then(data => {
                            uniform.video.src = URL.createObjectURL(data)
                          })
                        }
                      break
                      default:
                        uniform.textureMode = 'image'
                        if(0&&involveCache && (cacheItem=cache.textures.filter(v=>v.url==url)).length){
                          console.log('found image in cache... using it')
                          var image = cacheItem[0].resource
                          ret.datasets = [...ret.datasets, {texture: cacheItem[0].texture, iURL: url }]
                          gl.activeTexture(gl.TEXTURE1)
                          BindImage(gl, image, uniform.refTexture, uniform.textureMode, -1, url)
                        }else{
                          var image = new Image()
                          ret.datasets = [...ret.datasets, {
                            texture: uniform.refTexture, iURL: url }]
                          gl.activeTexture(gl.TEXTURE1)
                          gl.uniform1f(uniform.locRefFlipRefs , uniform.refTexture)
                          gl.bindTexture(gl.TEXTURE_2D, uniform.refTexture)
                          image.onload = () =>{
                            BindImage(gl, image, uniform.refTexture, uniform.textureMode, -1, url)
                          }
                          cache.textures.push({
                            url,
                            resource: image,
                            texture: uniform.refTexture
                          })
                          await fetch(url).then(res=>res.blob()).then(data => {
                            image.src = URL.createObjectURL(data)
                          })
                        }                        
                      break
                    }
                  }
                  gl.useProgram(dset.program)
                  gl.activeTexture(gl.TEXTURE1)
                  uniform.locRefOmitEquirectangular = gl.getUniformLocation(dset.program, "refOmitEquirectangular")
                  gl.uniform1f(uniform.locRefOmitEquirectangular,
                     ( geometry.shapeType == 'rectangle' ||
                       geometry.shapeType == 'point light' ||
                       geometry.shapeType == 'sprite' ||
                       geometry.shapeType == 'particles') ? 1.0 : 0.0)
                    //gl.uniform1f(uniform.locRefFlipRefs , uniform.refTexture)
                    //gl.bindTexture(gl.TEXTURE_2D, uniform.refTexture)
                  uniform.locRefFlipRefs = gl.getUniformLocation(dset.program, "refFlipRefs")
                  gl.uniform1f(uniform.locRefFlipRefs , uniform.flipReflections)
                  uniform.locRefTexture = gl.getUniformLocation(dset.program, "reflectionMap")
                  gl.bindTexture(gl.TEXTURE_2D, uniform.refTexture)
                  gl.uniform1i(uniform.locRefTexture, 1)
                  gl.activeTexture(gl.TEXTURE1)
                  gl.bindTexture(gl.TEXTURE_2D, uniform.refTexture)
                break
                case 'phong':
                  uniform.locPhongTheta = gl.getUniformLocation(dset.program, uniform.theta)
                  gl.uniform1f(uniform.locPhongTheta, uniform.theta)
                break
              }
              uniform.locFlatShading = gl.getUniformLocation(dset.program, uniform.flatShadingUniform)
              gl.uniform1f(uniform.locFlatShading , uniform.flatShading ? 1.0 : 0.0)
              
              uniform.loc = gl.getUniformLocation(dset.program, uniform.name)
              gl[uniform.dataType](uniform.loc, uniform.value)
            })
          }
          dset.locColor = gl.getUniformLocation(dset.program, "color")
          gl.uniform3f(dset.locColor, ...HexToRGB(geometry.color))
          
          if(geometry.shapeType == 'particles'){
            dset.locPointSize = gl.getUniformLocation(dset.program, "pointSize")
            gl.uniform1f(dset.locPointSize, geometry.size)
          }
          
          dset.locColorMix = gl.getUniformLocation(dset.program, "colorMix")
          gl.uniform1f(dset.locColorMix, geometry.colorMix)

          dset.locIsSprite = gl.getUniformLocation(dset.program, "isSprite")
          gl.uniform1f(dset.locIsSprite, geometry.isSprite ? 1.0 : 0.0)

          dset.locCameraMode = gl.getUniformLocation(dset.program, "cameraMode")
          gl.uniform1f(dset.locCameraMode, renderer.cameraMode.toLowerCase() == 'fps' ? 1.0 : 0.0)

          dset.locSupplementalTextureMix = gl.getUniformLocation(dset.program, "supplementalTextureMix")
          gl.uniform1f(dset.locSupplementalTextureMix, geometry.canvasTextureMix)

          dset.locIsLight = gl.getUniformLocation(dset.program, "isLight")
          gl.uniform1f(dset.locIsLight, geometry.isLight ? 1.0 : 0.0)

          dset.locIsParticle = gl.getUniformLocation(dset.program, "isParticle")
          gl.uniform1f(dset.locIsParticle, geometry.isParticle ? 1.0 : 0.0)

          dset.locPenumbraPass = gl.getUniformLocation(dset.program, "penumbraPass")
          //gl.uniform1f(dset.locPenumbraPass, geometry.penumbra)

          dset.locAlpha = gl.getUniformLocation(dset.program, "alpha")
          gl.uniform1f(dset.locAlpha, geometry.alpha)

          dset.locPointLights = gl.getUniformLocation(dset.program, "pointLightPos[0]")

          dset.locPointLightCols = gl.getUniformLocation(dset.program, "pointLightCol[0]")

          dset.locPointLightCount = gl.getUniformLocation(dset.program, "pointLightCount")
          gl.uniform1i(dset.locPointLightCount, 0)

          dset.locResolution = gl.getUniformLocation(dset.program, "resolution")
          gl.uniform2f(dset.locResolution, renderer.width, renderer.height)

          dset.locEquirectangular = gl.getUniformLocation(dset.program, "equirectangular")
          gl.uniform1f(dset.locEquirectangular, geometry.equirectangular ? 1.0 : 0.0)

          dset.locT = gl.getUniformLocation(dset.program, "t")
          gl.uniform1f(dset.locT, 0)

          dset.locAmbientLight = gl.getUniformLocation(dset.program, "ambientLight")
          gl.uniform1f(dset.locAmbientLight, renderer.ambientLight)

          dset.locRotationMode= gl.getUniformLocation(dset.program, "rotationMode")
          gl.uniform1i(dset.locRotationMode, geometry.rotationMode)

          dset.texture = gl.createTexture()
          gl.activeTexture(gl.TEXTURE0)
          gl.bindTexture(gl.TEXTURE_2D, dset.texture)
          dset.locTexture = gl.getUniformLocation(dset.program, "baseTexture")
          
          //if(renderer.optionalPlugins.length){
          //  renderer.locPlugin = gl.getUniformLocation(dset.program, "plugin")
          //  renderer.locOmitSplitCheck = gl.getUniformLocation(dset.program, "omitSplitCheck")
          //  renderer.locSplitCheckPass = gl.getUniformLocation(dset.program, "splitCheckPass")
          //}

          if(geometry.heightMap){
            dset.heightTexture = gl.createTexture()
            //gl.activeTexture(gl.TEXTURE4)
            //gl.bindTexture(gl.TEXTURE_2D, dset.heightTexture)
            dset.locHeightMap = gl.getUniformLocation(dset.program, "heightMap")
            dset.locEquirectangularHeightmap = gl.getUniformLocation(dset.program, "equirectangularHeightmap")
            dset.locUseHeightMap = gl.getUniformLocation(dset.program, "useHeightMap")
            dset.locHeightMapIntensity = gl.getUniformLocation(dset.program, "heightMapIntensity")
            gl.activeTexture(gl.TEXTURE0)
          }
          
          dset.supplementalTexture = gl.createTexture()
          gl.bindTexture(gl.TEXTURE_2D, dset.supplementalTexture)
          dset.locSupplementalTexture= gl.getUniformLocation(dset.program, "supplementalTexture")
          
          if(textureURL){
            dset.iURL = textureURL
            let l
            let suffix = (l=textureURL.split('.'))[l.length-1].toLowerCase()
            switch(suffix){
              case 'mp4': case 'webm': case 'avi': case 'mkv': case 'ogv':
                geometry.textureMode = 'video'
                if(involveCache && (cacheItem=cache.textures.filter(v=>v.url == dset.iURL)).length){
                  console.log('found video in cache... using it')
                  dset.resource = cacheItem[0].resource
                  //dset.resource.playbackRate = dset.resource.defaultPlaybackRate = geometry.playbackSpeed
                  dset.texture = cacheItem[0].texture
                  //gl.activeTexture(gl.TEXTURE0)
                  //BindImage(gl, dset.resource, dset.texture, geometry.textureMode, -1, textureURL)
                }else{
                  dset.resource = document.createElement('video')
                  dset.resource.muted = true
                  dset.resource.addEventListener('canplay', () =>{
                    dset.resource.playbackRate = dset.resource.defaultPlaybackRate = geometry.playbackSpeed
                  })
                  dset.resource.loop = true
                  if(!geometry.muted && !audioConsent) {
                    audioConsent = true
                    GenericPopup('play audio OK?', true, ()=>{
                      dset.resource.muted = false
                      dset.resource.currentTime = 0
                      //dset.resource.playbackRate = dset.resource.defaultPlaybackRate = dset.resource.playbackSpeed
                      dset.resource.play()
                    })
                  }
                  dset.resource.playbackRate = dset.resource.defaultPlaybackRate = geometry.playbackSpeed
                  dset.resource.oncanplay = async () => {
                    dset.resource.play()
                  }
                  //gl.activeTexture(gl.TEXTURE0)
                  //BindImage(gl, dset.resource, dset.texture, geometry.textureMode, -1, textureURL)
                  cache.textures.push({
                    url: textureURL,
                    resource: dset.resource,
                    texture: dset.texture
                  })
                  await fetch(textureURL).then(res=>res.blob()).then(data => {
                    dset.resource.src = URL.createObjectURL(data)
                  })
                }
              break
              default:
                geometry.textureMode = 'image'
                if(0&&involveCache && (cacheItem=cache.textures.filter(v=>v.url==textureURL)).length){
                  dset.texture = cacheItem[0].texture
                  var image = cacheItem[0].resource
                  dset.resource = image
                  gl.activeTexture(gl.TEXTURE0)
                  BindImage(gl, image, dset.texture, geometry.textureMode, -1, textureURL)
                }else{
                  var image = new Image()
                  cache.textures.push({
                    url: textureURL,
                    resource: image,
                    texture: dset.texture
                  })
                  image.onload = async () => {
                    gl.activeTexture(gl.TEXTURE0)
                    await BindImage(gl, image,
                      dset.texture, geometry.textureMode, -1, textureURL)
                  }
                  await fetch(textureURL).then(res=>res.blob()).then(data => {
                    image.src = URL.createObjectURL(data)
                  })
                }
              break
            }
          }
          gl.useProgram(dset.program)
          gl.activeTexture(gl.TEXTURE0)
          gl.uniform1i(dset.locTexture, 0)
          gl.bindTexture(gl.TEXTURE_2D, dset.texture)


          if(geometry.heightMapIsCanvas){
            dset.heightResource = heightMapURL
          }else if(heightMapURL){
            dset.heightMapURL = heightMapURL
            let l
            let suffix = (l=heightMapURL.split('.'))[l.length-1].toLowerCase()
            switch(suffix){
              case 'mp4': case 'webm': case 'avi': case 'mkv': case 'ogv':
                geometry.heightTextureMode = 'video'
                if(involveCache && (cacheItem=cache.textures.filter(v=>v.url == dset.heightMapURL)).length){
                  console.log('found video in cache... using it')
                  dset.heightResource = cacheItem[0].resource
                  //dset.heightResource.playbackRate = dset.heightResource.defaultPlaybackRate = geometry.playbackSpeed
                  dset.heightTexture = cacheItem[0].texture
                  //gl.activeTexture(gl.TEXTURE4)
                  //BindImage(gl, dset.heightResource, dset.heightTexture, geometry.heightTextureMode, -1, heightMapURL)
                }else{
                  dset.heightResource = document.createElement('video')
                  dset.heightResource.muted = true
                  dset.heightResource.addEventListener('canplay', () =>{
                    dset.heightResource.playbackRate = dset.heightResource.defaultPlaybackRate = geometry.playbackSpeed
                  })
                  dset.heightResource.loop = true
                  if(!geometry.muted && !audioConsent) {
                    audioConsent = true
                    GenericPopup('play audio OK?', true, ()=>{
                      dset.heightResource.muted = false
                      dset.heightResource.currentTime = 0
                      //dset.heightResource.playbackRate = dset.heightResource.defaultPlaybackRate = dset.heightResource.playbackSpeed
                      dset.heightResource.play()
                    })
                  }
                  dset.heightResource.playbackRate = dset.heightResource.defaultPlaybackRate = geometry.playbackSpeed
                  dset.heightResource.oncanplay = async () => {
                    dset.heightResource.play()
                  }
                  //gl.activeTexture(gl.TEXTURE4)
                  //BindImage(gl, dset.heightResource, dset.heightTexture, geometry.heightTextureMode, -1, heightMapURL)
                  cache.textures.push({
                    url: heightMapURL,
                    resource: dset.heightResource,
                    texture: dset.heightTexture
                  })
                  await fetch(heightMapURL).then(res=>res.blob()).then(data => {
                    dset.heightResource.src = URL.createObjectURL(data)
                  })
                }
              break
              default:
                geometry.heightTextureMode = 'heightImage'
                if(0&&involveCache && (cacheItem=cache.textures.filter(v=>v.url==heightMapURL)).length){
                  dset.heightTexture = cacheItem[0].texture
                  var heightImage = cacheItem[0].resource
                  dset.heightResource = heightImage
                  gl.activeTexture(gl.TEXTURE4)
                  BindImage(gl, heightImage, dset.heightTexture, geometry.heightTextureMode, -1, heightMapURL)
                }else{
                  var heightImage = new Image()
                  dset.heightResource = heightImage
                  cache.textures.push({
                    url: heightMapURL,
                    resource: heightImage,
                    texture: dset.heightTexture
                  })
                  
                  heightImage.onload = async () => {
                    gl.useProgram(dset.program)
                    gl.activeTexture(gl.TEXTURE4)
                    gl.uniform1i(dset.locHeightMap, 4)
                    await BindImage(gl, heightImage,
                       dset.heightTexture, geometry.heightTextureMode, -1, heightMapURL)
                    gl.activeTexture(gl.TEXTURE0)
                  }
                  
                  await fetch(heightMapURL).then(res=>res.blob()).then(data => {
                    heightImage.src = URL.createObjectURL(data)
                  })
                }
              break
            }
          }
          //gl.useProgram( dset.program )
          //gl.activeTexture(gl.TEXTURE4)
          //gl.uniform1i(dset.locHeightMap, 4)
          //gl.bindTexture(gl.TEXTURE_2D, dset.heightTexture)
          //gl.activeTexture(gl.TEXTURE0)
          
          
          gl.useProgram(dset.program)

          dset.locCamPos         = gl.getUniformLocation(dset.program, "camPos")
          dset.locCamOri         = gl.getUniformLocation(dset.program, "camOri")
          dset.locGeoPos         = gl.getUniformLocation(dset.program, "geoPos")
          dset.locGeoOri         = gl.getUniformLocation(dset.program, "geoOri")
          dset.locFov            = gl.getUniformLocation(dset.program, "fov")
          dset.locRenderNormals  = gl.getUniformLocation(dset.program, "renderNormals")
          gl.uniform3f(dset.locCamPos,        renderer.x, renderer.y, renderer.z)
          gl.uniform3f(dset.locCamOri,        renderer.roll, renderer.pitch, renderer.yaw)
          gl.uniform3f(dset.locGeoPos,        renderer.x, renderer.y, renderer.z)
          gl.uniform3f(dset.locGeoOri,        geometry.roll, geometry.pitch, geometry.yaw)
          gl.uniform1f(dset.locFov,           renderer.fov)
          gl.uniform1f(dset.locRenderNormals, 0)
        }
      }else{
        var info = gl.getProgramInfoLog(dset.program)
        var vshaderInfo = gl.getShaderInfoLog(vertexShader)
        var fshaderInfo = gl.getShaderInfoLog(fragmentShader)
        console.error(`bad shader :( ${info}`)
        console.error(`vShader info : ${vshaderInfo}`)
        console.error(`fShader info : ${fshaderInfo}`)
      }
    }
  }
  
  return ret
}

const IsPolyhedron = shapeType => {
  var isPolyhedron
  switch(shapeType){
    case 'tetrahedron'  : isPolyhedron = true; break
    case 'cube'         : isPolyhedron = true; break
    case 'octahedron'   : isPolyhedron = true; break
    case 'dodecahedron' : isPolyhedron = true; break
    case 'icosahedron'  : isPolyhedron = true; break
    case 'tetrahedron'  : isPolyhedron = true; break
    case 'dynamic'      : isPolyhedron = true; break
    default: isPolyhedron = false; break
  }
  return isPolyhedron
}

const GeometryFromRaw = async (raw, texCoords, size, subs,
                         sphereize, flipNormals,
                         quads=false, shapeType='') => {
  var j, i, X, Y, Z, b, l
  var a = new Float32Array()
  var f = new Float32Array()
  var e = raw
  var geometry = []
  
  var hint = `${shapeType}_${subs}`;
  var shape
  var isPolyhedron = IsPolyhedron(shapeType)
  switch(shapeType){
    case 'obj': shape = await subbed(0, 1, sphereize, e, texCoords, hint); break
    default: shape = await subbed(subs + (isPolyhedron?1:0), 1, sphereize, e, texCoords, hint); break
  }
  
  shape.map(v => {
    v.verts.map(q=>{
      X = q[0] *= size
      Y = q[1] *= size
      Z = q[2] *= size
    })
    if(quads){
      a = [...a, v.verts[0],v.verts[1],v.verts[2],
                 v.verts[2],v.verts[3],v.verts[0]]
      if(typeof v.uvs != 'undefined' && v.uvs.length)
          f = [...f, v.uvs[0],v.uvs[1],v.uvs[2],
                     v.uvs[2],v.uvs[3],v.uvs[0]]
    }else{
      a = [...a, ...v.verts]
      if(typeof v.uvs != 'undefined' && v.uvs.length)
        f = [...f, ...v.uvs]
    }
  })
  
  for(i = 0; i < a.length; i++){
    var normal
    j = i/3 | 0
    b = [a[j*3+0], a[j*3+1], a[j*3+2]]
    if(!(i%3)){
      normal = Normal(b, isPolyhedron)
      if(!flipNormals){
        normal[3] = normal[0] + (normal[0]-normal[3])
        normal[4] = normal[1] + (normal[1]-normal[4])
        normal[5] = normal[2] + (normal[2]-normal[5])
      }
    }
    l = flipNormals ? a.length - i - 1 : i
    geometry = [...geometry, {
      position: a[l],
      normal: [...a[l],
               a[l][0] + (normal[3]-normal[0]),
               a[l][1] + (normal[4]-normal[1]),
               a[l][2] + (normal[5]-normal[2])],
      texCoord: f[l],
    }]
  }
  
  return {
    geometry
  }
}

const subbed = async (subs, size, sphereize, shape, texCoords, hint='') => {
  
  var base, baseTexCoords, l, X, Y, Z
  var X1, Y1, Z1, X2, Y2, Z2, X3, Y3, Z3
  var X4, Y4, Z4, X5, Y5, Z5, X6, Y6, Z6
  var tX1, tY1, tX2, tY2, tX3, tY3
  var tX4, tY4, tX5, tY5, tX6, tY6
  var mx1, my1, mz1, mx2, my2, mz2, mx3, my3, mz3
  var mx4, my4, mz4, mx5, my5, mz5, mx6, my6, mz6
  var tmx1, tmy1, tmx2, tmy2, tmx3, tmy3
  var tmx4, tmy4, tmx5, tmy5, tmx6, tmy6
  var cx, cy, cz, ip1, ip2, a, ta
  var tcx, tcy, tv
  var resolved = false
  if(0 && subs > 1 && hint){
    var fileBase
    switch(hint){
      case 'tetrahedron_0': resolved = true; fileBase = hint; break
      case 'tetrahedron_1': resolved = true; fileBase = hint; break
      case 'tetrahedron_2': resolved = true; fileBase = hint; break
      case 'tetrahedron_3': resolved = true; fileBase = hint; break
      case 'tetrahedron_4': resolved = true; fileBase = hint; break
      case 'cube_0': resolved = true; fileBase = hint; break
      case 'cube_1': resolved = true; fileBase = hint; break
      case 'cube_2': resolved = true; fileBase = hint; break
      case 'cube_3': resolved = true; fileBase = hint; break
      case 'cube_4': resolved = true; fileBase = hint; break
      case 'octahedron_0': resolved = true; fileBase = hint; break
      case 'octahedron_1': resolved = true; fileBase = hint; break
      case 'octahedron_2': resolved = true; fileBase = hint; break
      case 'octahedron_3': resolved = true; fileBase = hint; break
      case 'octahedron_4': resolved = true; fileBase = hint; break
      case 'dodecahedron_0': resolved = true; fileBase = hint; break
      case 'dodecahedron_1': resolved = true; fileBase = hint; break
      case 'dodecahedron_2': resolved = true; fileBase = hint; break
      case 'dodecahedron_3': resolved = true; fileBase = hint; break
      case 'dodecahedron_4': resolved = true; fileBase = hint; break
      case 'icosahedron_0': resolved = true; fileBase = hint; break
      case 'icosahedron_1': resolved = true; fileBase = hint; break
      case 'icosahedron_2': resolved = true; fileBase = hint; break
      case 'icosahedron_3': resolved = true; fileBase = hint; break
      case 'icosahedron_4': resolved = true; fileBase = hint; break
    }
    
    if(resolved){
      var url = `${ModuleBase}/prebuilt%20shapes/`
      await fetch(`${url}${fileBase}_full.json`).then(res=>res.json()).then(data=>{
        shape     = data.shape
        texCoords = data.texCoords
      })
    }
  }
  if(!resolved){
    for(var m=subs; m--;){
      base = shape
      baseTexCoords = texCoords
      shape = new Float32Array()
      texCoords = new Float32Array()
      base.map((v, i) => {
        l = 0
        X1 = v[l][0]
        Y1 = v[l][1]
        Z1 = v[l][2]
        if(baseTexCoords.length && baseTexCoords[i].length>l){
          tv = baseTexCoords[i]
          tX1 = tv[l][0]
          tY1 = tv[l][1]
        }
        l = 1
        X2 = v[l][0]
        Y2 = v[l][1]
        Z2 = v[l][2]
        if(baseTexCoords.length && baseTexCoords[i].length>l){
          tX2 = tv[l][0]
          tY2 = tv[l][1]
        }
        l = 2
        X3 = v[l][0]
        Y3 = v[l][1]
        Z3 = v[l][2]
        if(baseTexCoords.length && baseTexCoords[i].length>l){
          tX3 = tv[l][0]
          tY3 = tv[l][1]
        }
        if(v.length > 3){
          l = 3
          X4 = v[l][0]
          Y4 = v[l][1]
          Z4 = v[l][2]
          if(baseTexCoords.length && baseTexCoords[i].length>l){
            tX4 = tv[l][0]
            tY4 = tv[l][1]
          }
          if(v.length > 4){
            l = 4
            X5 = v[l][0]
            Y5 = v[l][1]
            Z5 = v[l][2]
            if(baseTexCoords.length && baseTexCoords[i].length>l){
              tX5 = tv[l][0]
              tY5 = tv[l][1]
            }
            if(v.length > 5){
              l = 5
              X6 = v[l][0]
              Y6 = v[l][1]
              Z6 = v[l][2]
              if(baseTexCoords.length && baseTexCoords[i].length>l){
                tX6 = tv[l][0]
                tY6 = tv[l][1]
              }
            }
          }
        }
        mx1 = (X1+X2)/2
        my1 = (Y1+Y2)/2
        mz1 = (Z1+Z2)/2
        mx2 = (X2+X3)/2
        my2 = (Y2+Y3)/2
        mz2 = (Z2+Z3)/2

        if(typeof tX1 != 'undefined'){
          tmx1 = (tX1+tX2)/2
          tmy1 = (tY1+tY2)/2
          tmx2 = (tX2+tX3)/2
          tmy2 = (tY2+tY3)/2
        }
        a = new Float32Array()
        ta = new Float32Array()
        switch(v.length){
          case 3:
            mx3 = (X3+X1)/2
            my3 = (Y3+Y1)/2
            mz3 = (Z3+Z1)/2
            if(typeof tX1 != 'undefined'){
              tmx3 = (tX3+tX1)/2
              tmy3 = (tY3+tY1)/2
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tmx1, Y = tmy1, ta = [...ta, [X,Y]]
              X = tmx3, Y = tmy3, ta = [...ta, [X,Y]]
              texCoords= [...texCoords, ta]
              ta = new Float32Array()
              X = tmx1, Y = tmy1, ta = [...ta, [X,Y]]
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tmx2, Y = tmy2, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tmx3, Y = tmy3, ta = [...ta, [X,Y]]
              X = tmx2, Y = tmy2, ta = [...ta, [X,Y]]
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tmx1, Y = tmy1, ta = [...ta, [X,Y]]
              X = tmx2, Y = tmy2, ta = [...ta, [X,Y]]
              X = tmx3, Y = tmy3, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
            }
            
            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = mx1, Y = my1, Z = mz1, a = [...a, [X,Y,Z]]
            X = mx3, Y = my3, Z = mz3, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = mx1, Y = my1, Z = mz1, a = [...a, [X,Y,Z]]
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = mx2, Y = my2, Z = mz2, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = mx3, Y = my3, Z = mz3, a = [...a, [X,Y,Z]]
            X = mx2, Y = my2, Z = mz2, a = [...a, [X,Y,Z]]
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = mx1, Y = my1, Z = mz1, a = [...a, [X,Y,Z]]
            X = mx2, Y = my2, Z = mz2, a = [...a, [X,Y,Z]]
            X = mx3, Y = my3, Z = mz3, a = [...a, [X,Y,Z]]
            shape = [...shape, a]

            break
          case 4:
            mx3 = (X3+X4)/2
            my3 = (Y3+Y4)/2
            mz3 = (Z3+Z4)/2
            mx4 = (X4+X1)/2
            my4 = (Y4+Y1)/2
            mz4 = (Z4+Z1)/2
            if(typeof tX1 != 'undefined'){
              tmx3 = (tX3+tX4)/2
              tmy3 = (tY3+tY4)/2
              tmx4 = (tX4+tX1)/2
              tmy4 = (tY4+tY1)/2
              tcx = (tX1+tX2+tX3+tX4)/4
              tcy = (tY1+tY2+tY3+tY4)/4
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tmx1, Y = tmy1, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              X = tmx4, Y = tmy4, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tmx1, Y = tmy1, ta = [...ta, [X,Y]]
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tmx2, Y = tmy2, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              X = tmx2, Y = tmy2, ta = [...ta, [X,Y]]
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              X = tmx3, Y = tmy3, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tmx4, Y = tmy4, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              X = tmx3, Y = tmy3, ta = [...ta, [X,Y]]
              X = tX4, Y = tY4, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
            }

            cx = (X1+X2+X3+X4)/4
            cy = (Y1+Y2+Y3+Y4)/4
            cz = (Z1+Z2+Z3+Z4)/4

            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = mx1, Y = my1, Z = mz1, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            X = mx4, Y = my4, Z = mz4, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = mx1, Y = my1, Z = mz1, a = [...a, [X,Y,Z]]
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = mx2, Y = my2, Z = mz2, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            X = mx2, Y = my2, Z = mz2, a = [...a, [X,Y,Z]]
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            X = mx3, Y = my3, Z = mz3, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = mx4, Y = my4, Z = mz4, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            X = mx3, Y = my3, Z = mz3, a = [...a, [X,Y,Z]]
            X = X4, Y = Y4, Z = Z4, a = [...a, [X,Y,Z]]
            shape = [...shape, a]

            break
          case 5:
            cx = (X1+X2+X3+X4+X5)/5
            cy = (Y1+Y2+Y3+Y4+Y5)/5
            cz = (Z1+Z2+Z3+Z4+Z5)/5

            if(typeof tX1 != 'undefined'){
              tcx = (tX1+tX2+tX3+tX4+tX5)/5
              tcy = (tY1+tY2+tY3+tY4+tY5)/5
              tmx3 = (tX3+tX4)/2
              tmy3 = (tY3+tY4)/2
              tmx4 = (tX4+tX5)/2
              tmy4 = (tY4+tY5)/2
              tmx5 = (tX5+tX1)/2
              tmy5 = (tY5+tY1)/2
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              X = tX4, Y = tY4, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX4, Y = tY4, ta = [...ta, [X,Y]]
              X = tX5, Y = tY5, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX5, Y = tY5, ta = [...ta, [X,Y]]
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
            }

            mx3 = (X3+X4)/2
            my3 = (Y3+Y4)/2
            mz3 = (Z3+Z4)/2
            mx4 = (X4+X5)/2
            my4 = (Y4+Y5)/2
            mz4 = (Z4+Z5)/2
            mx5 = (X5+X1)/2
            my5 = (Y5+Y1)/2
            mz5 = (Z5+Z1)/2

            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            X = X4, Y = Y4, Z = Z4, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = X4, Y = Y4, Z = Z4, a = [...a, [X,Y,Z]]
            X = X5, Y = Y5, Z = Z5, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = X5, Y = Y5, Z = Z5, a = [...a, [X,Y,Z]]
            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

          break
          case 6:
            cx = (X1+X2+X3+X4+X5)/5
            cy = (Y1+Y2+Y3+Y4+Y5)/5
            cz = (Z1+Z2+Z3+Z4+Z5)/5

            if(typeof tX1 != 'undefined'){
              tcx = (tX1+tX2+tX3+tX4+tX5+tX6)/6
              tcy = (tY1+tY2+tY3+tY4+tY5+tY6)/6
              tmx3 = (tX3+tX4)/2
              tmy3 = (tY3+tY4)/2
              tmx4 = (tX4+tX5)/2
              tmy4 = (tY4+tY5)/2
              tmx5 = (tX5+tX6)/2
              tmy5 = (tY5+tY6)/2
              tmx6 = (tX6+tX1)/2
              tmy6 = (tY6+tY1)/2
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX2, Y = tY2, ta = [...ta, [X,Y]]
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX3, Y = tY3, ta = [...ta, [X,Y]]
              X = tX4, Y = tY4, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX4, Y = tY4, ta = [...ta, [X,Y]]
              X = tX5, Y = tY5, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX5, Y = tY5, ta = [...ta, [X,Y]]
              X = tX6, Y = tY6, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
              X = tX6, Y = tY6, ta = [...ta, [X,Y]]
              X = tX1, Y = tY1, ta = [...ta, [X,Y]]
              X = tcx, Y = tcy, ta = [...ta, [X,Y]]
              texCoords = [...texCoords, ta]
              ta = new Float32Array()
            }

            mx3 = (X3+X4)/2
            my3 = (Y3+Y4)/2
            mz3 = (Z3+Z4)/2
            mx4 = (X4+X5)/2
            my4 = (Y4+Y5)/2
            mz4 = (Z4+Z5)/2
            mx5 = (X5+X6)/2
            my5 = (Y5+Y6)/2
            mz5 = (Z5+Z6)/2
            mx6 = (X6+X1)/2
            my6 = (Y6+Y1)/2
            mz6 = (Z6+Z1)/2

            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = X2, Y = Y2, Z = Z2, a = [...a, [X,Y,Z]]
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()
            
            X = X3, Y = Y3, Z = Z3, a = [...a, [X,Y,Z]]
            X = X4, Y = Y4, Z = Z4, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = X4, Y = Y4, Z = Z4, a = [...a, [X,Y,Z]]
            X = X5, Y = Y5, Z = Z5, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = X5, Y = Y5, Z = Z5, a = [...a, [X,Y,Z]]
            X = X6, Y = Y6, Z = Z6, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

            X = X6, Y = Y6, Z = Z6, a = [...a, [X,Y,Z]]
            X = X1, Y = Y1, Z = Z1, a = [...a, [X,Y,Z]]
            X = cx, Y = cy, Z = cz, a = [...a, [X,Y,Z]]
            shape = [...shape, a]
            a = new Float32Array()

          break
        }
      })
    }
  }

  if(sphereize){
    var d, val
    ip1 = sphereize
    ip2 = 1-sphereize
    for(var m=2; m--;) {
      (m ? shape : texCoords).map(v=>{
        v.map(q=>{
          X = q[0]
          Y = q[1]
          Z = m ? q[2] : 0
          d = Math.hypot(X,Y,Z)
          X /= d
          Y /= d
          Z /= d
          q[0]       = X *= ip1 + d*ip2
          q[1]       = Y *= ip1 + d*ip2
          if(m) q[2] = Z *= ip1 + d*ip2
        })
      })
    }
  }
  
  return shape.map((v, i) => {
    return {
      verts: v,
      uvs: texCoords[i]
    }
  })
}


const Camera = (x=0, y=0, z=0, roll=0, pitch=0, yaw=0) => ({ x, y, z, roll, pitch, yaw })

const GeoSphere = (mx, my, mz, iBc, size) => {
  let X, Y, Z, X1, Y1, Z1, X2, Y2, Z2
  let collapse=0, mind, d, a, b, e
  let B=Array(iBc).fill().map(v=>{
    X = Rn()-.5
    Y = Rn()-.5
    Z = Rn()-.5
    return  [X,Y,Z]
  })
  for(let m=50;m--;){
    B.map((v,i)=>{
      X = v[0]
      Y = v[1]
      Z = v[2]
      B.map((q,j)=>{
        if(j!=i){
          X2=q[0]
          Y2=q[1]
          Z2=q[2]
          d=1+(Math.hypot(X-X2,Y-Y2,Z-Z2)*(3+iBc/80)*3)**3
          X+=(X-X2)*9/d
          Y+=(Y-Y2)*9/d
          Z+=(Z-Z2)*9/d
        }
      })
      d=Math.hypot(X,Y,Z)
      v[0]=X/d
      v[1]=Y/d
      v[2]=Z/d
      if(collapse){
        d=25+Math.hypot(X,Y,Z)
        v[0]=(X-X/d)/1.1
        v[1]=(Y-Y/d)/1.1         
        v[2]=(Z-Z/d)/1.1
      }
    })
  }
  mind = 6e6
  B.map((v,i)=>{
    X1 = v[0]
    Y1 = v[1]
    Z1 = v[2]
    B.map((q,j)=>{
      X2 = q[0]
      Y2 = q[1]
      Z2 = q[2]
      if(i!=j){
        d = Math.hypot(a=X1-X2, b=Y1-Y2, e=Z1-Z2)
        if(d<mind) mind = d
      }
    })
  })
  a = new Float32Array()
  B.map((v,i)=>{
    X1 = v[0]
    Y1 = v[1]
    Z1 = v[2]
    B.map((q,j)=>{
      X2 = q[0]
      Y2 = q[1]
      Z2 = q[2]
      if(i!=j){
        d = Math.hypot(X1-X2, Y1-Y2, Z1-Z2)
        if(d<mind*2){
          if(!a.filter(q=>q[0]==X2&&q[1]==Y2&&q[2]==Z2&&q[3]==X1&&q[4]==Y1&&q[5]==Z1).length) a = [...a, [X1*size,Y1*size,Z1*size,X2*size,Y2*size,Z2*size]]
        }
      }
    })
  })
  B.map(v=>{
    v[0]*=size/1.3333
    v[1]*=size/1.3333
    v[2]*=size/1.3333
    v[0]+=mx
    v[1]+=my
    v[2]+=mz
  })
  return [mx, my, mz, size, B, a]
}

const Cylinder = async (size = 1, subs = 0, rw, cl, sphereize = 0, flipNormals=false, shapeType='cylinder') => {
  var ret = new Float32Array()
  var X1,Y1,Z1, X2,Y2,Z2, X3,Y3,Z3, X4,Y4,Z4
  var TX1,TY1, TX2,TY2, TX3,TY3, TX4,TY4
  var p
  var texCoords = new Float32Array()
  for(var j = 0; j < rw; j++){
    var j2 = j-.5
    for(var i = 0; i < cl; i++){
      X1 = S(p=Math.PI*2/cl*i)
      Y1 = -.5 + 1/rw*j2
      Z1 = C(p=Math.PI*2/cl*i)
      X2 = S(p=Math.PI*2/cl*(i+1))
      Y2 = -.5 + 1/rw*j2
      Z2 = C(p=Math.PI*2/cl*(i+1))
      X3 = S(p=Math.PI*2/cl*(i+1))
      Y3 = -.5 + 1/rw*(j2+1)
      Z3 = C(p=Math.PI*2/cl*(i+1))
      X4 = S(p=Math.PI*2/cl*i)
      Y4 = -.5 + 1/rw*(j2+1)
      Z4 = C(p=Math.PI*2/cl*i)
      
      var p1 = Math.atan2(X1,Z1)
      var p2 = Math.atan2(X2,Z2)
      var p3 = Math.atan2(X3,Z3)
      var p4 = Math.atan2(X4,Z4)
      
      if(Math.abs(p1-p2) > Math.PI){
        p1 -= Math.PI*2
        p4 -= Math.PI*2
      }
      
      TX1 = (p1+Math.PI) / Math.PI / 2
      TY1 = Y1 + .5
      TX2 = (p2+Math.PI) / Math.PI / 2
      TY2 = Y2 + .5
      TX3 = (p3+Math.PI) / Math.PI / 2
      TY3 = Y3 + .5
      TX4 = (p4+Math.PI) / Math.PI / 2
      TY4 = Y4 + .5
      
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3], [X4,Y4,Z4]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3], [TX4,TY4]]]
      
      /* //triangulate
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3]]]
      ret = [...ret, [[X3,Y3,Z3], [X4,Y4,Z4], [X1,Y1,Z1]]]
      texCoords = [...texCoords, [[TX3,TY3], [TX4,TY4], [TX1,TY1]]]
      */
    }
  }
  return await GeometryFromRaw(ret, texCoords, size / 1.2, subs,
                         sphereize, flipNormals, true, shapeType)
}

const Torus = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='torus', rw, cl) => {
  var ret = new Float32Array()
  var X, Y, Z
  var X1,Y1,Z1, X2,Y2,Z2, X3,Y3,Z3, X4,Y4,Z4
  var TX1,TY1, TX2,TY2, TX3,TY3, TX4,TY4
  var p, d
  var texCoords = new Float32Array()
  var rw_ = rw * 4
  var rad1 = .5
  var rad2 = 1.25
  for(var j = 0; j < rw_; j++){
    var j2 = j+.5
    for(var i = 0; i < cl; i++){

      X = S(p=Math.PI*2/cl*i) * rad1 + rad2
      Y = C(p) * rad1
      Z = 0
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2 + Math.PI/2
      d = Math.hypot(X, Z)
      X1 = S(p) * d
      Y1 = Y
      Z1 = C(p) * d

      X = S(p=Math.PI*2/cl*(i+1)) * rad1 + rad2
      Y = C(p) * rad1
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2 + Math.PI/2
      d = Math.hypot(X, Z)
      X2 = S(p) * d
      Y2 = Y
      Z2 = C(p) * d

      X = S(p=Math.PI*2/cl*(i+1)) * rad1 + rad2
      Y = C(p) * rad1
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * (j2+1) + Math.PI/2
      d = Math.hypot(X, Z)
      X3 = S(p) * d
      Y3 = Y
      Z3 = C(p) * d

      X = S(p=Math.PI*2/cl*i) * rad1 + rad2
      Y = C(p) * rad1
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * (j2+1) + Math.PI/2
      d = Math.hypot(X, Z)
      X4 = S(p) * d
      Y4 = Y
      Z4 = C(p) * d

      var p1 = Math.atan2(X1,Z1)
      var p2 = Math.atan2(X2,Z2)
      var p3 = Math.atan2(X3,Z3)
      var p4 = Math.atan2(X4,Z4)
      
      if(Math.abs(p1-p3) > Math.PI){
        p3 += Math.PI*2
        p4 += Math.PI*2
      }
      
      TX1 = (p1+Math.PI) / Math.PI / 2
      TY1 = Y1 + .5
      TX2 = (p2+Math.PI) / Math.PI / 2
      TY2 = Y2 + .5
      TX3 = (p3+Math.PI) / Math.PI / 2
      TY3 = Y3 + .5
      TX4 = (p4+Math.PI) / Math.PI / 2
      TY4 = Y4 + .5
      
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3], [X4,Y4,Z4]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3], [TX4,TY4]]]
      
      /* //triangulate
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3]]]
      ret = [...ret, [[X3,Y3,Z3], [X4,Y4,Z4], [X1,Y1,Z1]]]
      texCoords = [...texCoords, [[TX3,TY3], [TX4,TY4], [TX1,TY1]]]
      */
    }
  }
  return await GeometryFromRaw(ret, texCoords, size / 1.2, subs,
                         sphereize, flipNormals, true, shapeType)
}

const TorusKnot = async (size = 1, subs = 0, rw, cl, sphereize = 0, flipNormals=false, shapeType='torus knot') => {
  var ret = new Float32Array()
  var X, Y, Z
  var X1,Y1,Z1, X2,Y2,Z2, X3,Y3,Z3, X4,Y4,Z4
  var TX1,TY1, TX2,TY2, TX3,TY3, TX4,TY4
  var p, d
  var texCoords = new Float32Array()
  var rw_ = rw * 8
  cl /= 1
  var rad1 = .75, p1, p2, p1a, p1b, p1c, p2a, p2b
  var oya, oyb, oyc
  var tRad1 = 3
  var twists = 1
  for(var j = 0; j < rw_ * 2; j++){
    for(var i = 0; i < cl; i++){
      
      var j2a = j+.5
      var j2b = j+1.5
      var j2c = j+2.5

      X1 = tRad1 + S(p1a=Math.PI*2*1.5*twists/rw_*j2a) /1
      Y1 = oya = C(p1a) * 2
      X2 = tRad1 + S(p1b=Math.PI*2*1.5*twists/rw_*j2b) /1
      Y2 = oyb = C(p1b) * 2
      X3 = tRad1 + S(p1c=Math.PI*2*1.5*twists/rw_*j2c) /1
      Y3 = oyc = C(p1c) * 2
      
      p2a = (Math.acos((Y2-Y1) / (Math.hypot(X2-X1, Y2-Y1)+.0001)) - Math.PI/2) / 2
      p2b = (Math.acos((Y3-Y2) / (Math.hypot(X3-X2, Y3-Y2)+.0001)) - Math.PI/2) / 2

      var rad2 = tRad1 + S(p1a) /1
      X = S(p=Math.PI*2/cl*i) * rad1 + rad2
      Y = C(p) * rad1 + oya
      Z = 0
      p = Math.atan2(Y, Z) + p2a
      d = Math.hypot(Y, Z)
      Y = S(p) * d
      Z = C(p) * d
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2a + Math.PI/2
      d = Math.hypot(X, Z)
      X1 = S(p) * d
      Y1 = Y
      Z1 = C(p) * d

      X = S(p=Math.PI*2/cl*(i+1)) * rad1 + rad2
      Y = C(p) * rad1 + oya
      Z = 0
      p = Math.atan2(Y, Z) + p2a
      d = Math.hypot(Y, Z)
      Y = S(p) * d
      Z = C(p) * d
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2a + Math.PI/2
      d = Math.hypot(X, Z)
      X2 = S(p) * d
      Y2 = Y
      Z2 = C(p) * d

      rad2 = tRad1 + S(p1b) /1
      X = S(p=Math.PI*2/cl*(i+1)) * rad1 + rad2
      Y = C(p) * rad1 + oyb
      Z = 0
      p = Math.atan2(Y, Z) + p2b
      d = Math.hypot(Y, Z)
      Y = S(p) * d
      Z = C(p) * d
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2b + Math.PI/2
      d = Math.hypot(X, Z)
      X3 = S(p) * d
      Y3 = Y
      Z3 = C(p) * d

      X = S(p=Math.PI*2/cl*i) * rad1 + rad2
      Y = C(p) * rad1 + oyb
      Z = 0
      p = Math.atan2(Y, Z) + p2b
      d = Math.hypot(Y, Z)
      Y = S(p) * d
      Z = C(p) * d
      p = Math.atan2(X, Z) + Math.PI*2/rw_ * j2b + Math.PI/2
      d = Math.hypot(X, Z)
      X4 = S(p) * d
      Y4 = Y
      Z4 = C(p) * d

      var p1 = Math.atan2(X1,Z1)
      var p2 = Math.atan2(X2,Z2)
      var p3 = Math.atan2(X3,Z3)
      var p4 = Math.atan2(X4,Z4)
      
      if(Math.abs(p1-p3) > Math.PI){
        p3 += Math.PI*2
        p4 += Math.PI*2
      }
      
      TX1 = (p1+Math.PI) / Math.PI / 2
      TY1 = Y1 + .5
      TX2 = (p2+Math.PI) / Math.PI / 2
      TY2 = Y2 + .5
      TX3 = (p3+Math.PI) / Math.PI / 2
      TY3 = Y3 + .5
      TX4 = (p4+Math.PI) / Math.PI / 2
      TY4 = Y4 + .5
      
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3], [X4,Y4,Z4]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3], [TX4,TY4]]]
      
      /* //triangulate
      ret = [...ret, [[X1,Y1,Z1], [X2,Y2,Z2], [X3,Y3,Z3]]]
      texCoords = [...texCoords, [[TX1,TY1], [TX2,TY2], [TX3,TY3]]]
      ret = [...ret, [[X3,Y3,Z3], [X4,Y4,Z4], [X1,Y1,Z1]]]
      texCoords = [...texCoords, [[TX3,TY3], [TX4,TY4], [TX1,TY1]]]
      */
    }
  }
  return await GeometryFromRaw(ret, texCoords, size / 1.2, subs,
                         sphereize, flipNormals, true, shapeType)
}



const Tetrahedron = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='tetrahedron') => {
  var X, Y, Z, p, tx, ty, ax, ay, az
  var f, i, j, l, a, b, ct, sz = 1
  var geometry = new Float32Array()
  var ret = new Float32Array()
  a = new Float32Array()
  let h = sz/1.4142/1.25
  for(i=3;i--;){
    X = S(p=Math.PI*2/3*i) * sz/1.25
    Y = C(p) * sz/1.25
    Z = h
    a = [...a, [X,Y,Z]]
  }
  ret = [...ret, a]
  for(j=3;j--;){
    a = new Float32Array()
    X = 0
    Y = 0
    Z = -h
    a = [...a, [X,Y,Z]]
    X = S(p=Math.PI*2/3*j) * sz/1.25
    Y = C(p) * sz/1.25
    Z = h
    a = [...a, [X,Y,Z]]
    X = S(p=Math.PI*2/3*(j+1)) * sz/1.25
    Y = C(p) * sz/1.25
    Z = h
    a = [...a, [X,Y,Z]]
    ret = [...ret, a]
  }
  ax=ay=az=ct=0
  ret.map(v=>{
    v.map(q=>{
      ax+=q[0]
      ay+=q[1]
      az+=q[2]
      ct++
    })
  })
  ax/=ct
  ay/=ct
  az/=ct
  ret.map(v=>{
    v.map(q=>{
      q[0]-=ax
      q[1]-=ay
      q[2]-=az
    })
  })

  var e = ret
  var texCoords = new Float32Array()
  for(i = 0; i < e.length; i++){
    a = new Float32Array()
    for(var k = e[i].length; k--;){
      switch(k) {
        case 0: tx=0, ty=0; break
        case 1: tx=1, ty=0; break
        case 2: tx=1, ty=1; break
        case 3: tx=0, ty=.5; break
        case 4: tx=0, ty=1; break
      }
      a = [...a, [tx, ty]]
    }
    texCoords = [...texCoords, a]
  }
  
  return await GeometryFromRaw(e, texCoords, size, subs,
                         sphereize, flipNormals, false, shapeType)
 }

const Octahedron = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='octahedron') => {
  var X, Y, Z, p, tx, ty
  var f, i, j, l, a, b, sz = 1
  var geometry = new Float32Array()
  var ret = new Float32Array()
  let h = sz/1.25
  for(j=8;j--;){
    a = new Float32Array()
    X = 0
    Y = 0
    Z = h * (j<4?-1:1)
    a = [...a, [X,Y,Z]]
    X = S(p=Math.PI*2/4*j) * sz/1.25
    Y = C(p) * sz/1.25
    Z = 0
    a = [...a, [X,Y,Z]]
    X = S(p=Math.PI*2/4*(j+1)) * sz/1.25
    Y = C(p) * sz/1.25
    Z = 0
    a = [...a, [X,Y,Z]]
    ret = [...ret, a]
  }
  
  var e = ret
  var texCoords = new Float32Array()
  for(i = 0; i < e.length; i++){
    a = new Float32Array()
    for(var k = e[i].length; k--;){
      switch(k) {
        case 0: tx=0, ty=0; break
        case 1: tx=1, ty=0; break
        case 2: tx=1, ty=1; break
        case 3: tx=0, ty=.5; break
        case 4: tx=0, ty=1; break
      }
      a = [...a, [tx, ty]]
    }
    texCoords = [...texCoords, a]
  }
  
  return await GeometryFromRaw(e, texCoords, size, subs,
                         sphereize, flipNormals, false, shapeType)
}

    
const Icosahedron = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='icosahedron') => {
  var i, X, Y, Z, d1, b, p, r, tx, ty
  var out, f, j, l, phi, a, cp
  var idx1a, idx2a, idx3a
  var idx1b, idx2b, idx3b
  var geometry = new Float32Array()
  var ret = new Float32Array()

  let B = [
    [[0,3],[1,0],[2,2]],
    [[0,3],[1,0],[1,3]],
    [[0,3],[2,3],[1,3]],
    [[0,2],[2,1],[1,0]],
    [[0,2],[1,3],[1,0]],
    [[0,2],[1,3],[2,0]],
    [[0,3],[2,2],[0,0]],
    [[1,0],[2,2],[2,1]],
    [[1,1],[2,2],[2,1]],
    [[1,1],[2,2],[0,0]],
    [[1,1],[2,1],[0,1]],
    [[0,2],[2,1],[0,1]],
    [[2,0],[1,2],[2,3]],
    [[0,0],[0,3],[2,3]],
    [[1,3],[2,0],[2,3]],
    [[2,3],[0,0],[1,2]],
    [[1,2],[2,0],[0,1]],
    [[0,0],[1,2],[1,1]],
    [[0,1],[1,2],[1,1]],
    [[0,2],[2,0],[0,1]],
  ]
  phi = .5+5**.5/2  //p[l]/p[l-1]
  a = [
    [-phi,-1,0],
    [phi,-1,0],
    [phi,1,0],
    [-phi,1,0],
  ]
  for(j=3;j--;ret=[...ret, b])for(b=[],i=4;i--;) b = [...b, [a[i][j],a[i][(j+1)%3],a[i][(j+2)%3]]]
  ret.map(v=>{
    v.map(q=>{
      q[0]*=1/2.25
      q[1]*=1/2.25
      q[2]*=1/2.25
    })
  })
  cp = JSON.parse(JSON.stringify(ret))
  out=[]
  a = new Float32Array()
  B.map(v=>{
    idx1a = v[0][0]
    idx2a = v[1][0]
    idx3a = v[2][0]
    idx1b = v[0][1]
    idx2b = v[1][1]
    idx3b = v[2][1]
    a = [...a, [cp[idx1a][idx1b],cp[idx2a][idx2b],cp[idx3a][idx3b]]]
  })
  out = [...out, ...a]

  var e = out
  var texCoords = new Float32Array()
  for(i = 0; i < e.length; i++){
    a = new Float32Array()
    for(var k = e[i].length; k--;){
      switch(k) {
        case 0: tx=0, ty=0; break
        case 1: tx=1, ty=0; break
        case 2: tx=1, ty=1; break
        case 3: tx=0, ty=.5; break
        case 4: tx=0, ty=1; break
      }
      a = [...a, [tx, ty]]
    }
    texCoords = [...texCoords, a]
  }
  
  return await GeometryFromRaw(e, texCoords, size, subs,
                         sphereize, flipNormals, false, shapeType)
}

const Dodecahedron = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='dodecahedron') => {
  var i, X, Y, Z, d1, b, p, r, tx, ty, f, i, j, l
  var ret = new Float32Array()
  var a = new Float32Array()
  let mind = -6e6
  for(i=5;i--;){
    X=S(p=Math.PI*2/5*i + Math.PI/5)
    Y=C(p)
    Z=0
    if(Y>mind) mind=Y
    a = [...a, [X,Y,Z]]
  }
  a=a.map(v=>{
    X = v[0]
    Y = v[1]-=mind
    Z = v[2]
    return R(X, Y, Z, {x:0, y:0, z:0,
                       roll:  0,
                       pitch: .553573,
                       yaw:   0})
  })
  b = structuredClone(a)
  b.map(v=>{
    v[1] *= -1
  })
  ret = [...ret, a, b]
  mind = -6e6
  ret.map(v=>{
    v.map(q=>{
      X = q[0]
      Y = q[1]
      Z = q[2]
      if(Z>mind)mind = Z
    })
  })
  d1=Math.hypot(ret[0][0][0]-ret[0][1][0],ret[0][0][1]-ret[0][1][1],ret[0][0][2]-ret[0][1][2])
  ret.map(v=>{
    v.map(q=>{
      q[2]-=mind+d1/2
    })
  })
  b = structuredClone(ret)
  b.map(v=>{
    v.map(q=>{
      q[2]*=-1
    })
  })
  ret = [...ret, ...b]
  b = structuredClone(ret)
  b.map(v=>{
    v.map(q=>{
      X = q[0]
      Y = q[1]
      Z = q[2]
      r = R_ypr(X, Y, Z, {x:0, y:0, z:0,
                         roll:  0,
                         pitch: Math.PI/2,
                         yaw:   Math.PI/2})
      
      q[0] = r[0]
      q[1] = r[1]
      q[2] = r[2]
    })
  })
  e = structuredClone(ret)
  e.map(v=>{
    v.map(q=>{
      X = q[0]
      Y = q[1]
      Z = q[2]
      r = R_ypr(X, Y, Z, {x:0, y:0, z:0,
                         roll:  Math.PI/2,
                         pitch: 0,
                         yaw:   Math.PI/2})
      
      q[0] = r[0]
      q[1] = r[1]
      q[2] = r[2]
    })
  })
  ret = [...ret, ...b, ...e]
  
  var e = ret
  var texCoords = new Float32Array()
  for(i = 0; i < e.length; i++){
    a = new Float32Array()
    for(var k = e[i].length; k--;){
      switch(k) {
        case 0: tx=0, ty=0; break
        case 1: tx=1, ty=0; break
        case 2: tx=1, ty=1; break
        case 3: tx=0, ty=.5; break
        case 4: tx=0, ty=1; break
      }
      a = [...a, [tx, ty]]
    }
    texCoords = [...texCoords, a]
  }
  
  return await GeometryFromRaw(e, texCoords, size / Math.max(1, (2 - sphereize)), subs,
                         sphereize, flipNormals, false, shapeType)
}




const Cube = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='cube') => {
  var p, pi=Math.PI, a, b, l, i, j, k, tx, ty, X, Y, Z
  var position, texCoord
  var geometry = new Float32Array()
  var e = new Float32Array(), f
  for(i=6; i--; e=[...e, b])for(b=[], j=4;j--;) b=[...b, [(a=[S(p=pi*2/4*j+pi/4), C(p), 2**.5/2])[i%3]*(l=i<3?1:-1),a[(i+1)%3]*l,a[(i+2)%3]*l]]
  
  var texCoords = new Float32Array()
  for(i = 0; i < e.length; i++){
    a = new Float32Array()
    for(var k = e[i].length; k--;){
      switch(k) {
        case 0: tx=0, ty=0; break
        case 1: tx=1, ty=0; break
        case 2: tx=1, ty=1; break
        case 3: tx=0, ty=1; break
      }
      a = [...a, [tx, ty]]
    }
    texCoords = [...texCoords, a]
  }
  
  let ret = await GeometryFromRaw(e, texCoords, size / 1.2, subs,
                         sphereize, flipNormals, true, shapeType)
                         
  return ret
}

const Rectangle = async (size = 1, subs = 0, sphereize = 0, flipNormals=false, shapeType='rectangle') => {
  var p, pi=Math.PI, a, b, l, i, j, k, tx, ty, X, Y, Z
  var position, texCoord
  var geometry = new Float32Array()
  var e = new Float32Array()

  e = [[
        [-1, -1, 0],
        [1, -1, 0],
        [1, 1, 0],
        [-1, 1, 0],
      ]]
  var texCoords = [[
    [0, 0],
    [1, 0],
    [1, 1],
    [0, 1],
  ]]
  
  var ret = await GeometryFromRaw(e, texCoords, size / 1.5,
       Math.max(shapeType == 'sprite' ? 1 : 2, subs),
             shapeType == 'sprite' ? .1 : sphereize, flipNormals, true, shapeType)
             
  return ret
}

const IsPowerOf2 = (v, d=0) => {
  if(d>300) return false
  if(v==2) return true
  return IsPowerOf2(v/2, d+1)
}

const PointInPoly3D = (X1, Y1, Z1, X2, Y2, Z2, facet, autoFlipNormals=false) => {
  let X_, Y_, Z_, d, m, l_,K,J,L,p
  let I_=(A,B,M,D,E,F,G,H)=>(K=((G-E)*(B-F)-(H-F)*(A-E))/(J=(H-F)*(M-A)-(G-E)*(D-B)))>=0&&K<=1&&(L=((M-A)*(B-F)-(D-B)*(A-E))/J)>=0&&L<=1?[A+K*(M-A),B+K*(D-B)]:0
  let Q_= () => [c.width/2+X_/Z_*700, c.height/2+Y_/Z_*700]
  let R_ = (Rl,Pt,Yw,m)=>{
    let M=Math, A=M.atan2, H=M.hypot
    X_ = S(p=A(X_,Y_)+Rl) * (d=H(X_,Y_))
    Y_ = C(p) * d
    X_ = S(p=A(X_,Z_)+Yw) * (d=H(X_,Z_))
    Z_ = C(p)*d
    Y_ = S(p=A(Y_,Z_)+Pt) * (d=H(Y_,Z_))
    Z_ = C(p)*d
    if(m){ X_+=oX,Y_+=oY,Z_+=oZ }
  }
  let rotSwitch = m =>{
    switch(m){
      case 0: R_(0,0,Math.PI/2); break
      case 1: R_(0,Math.PI/2,0); break
      case 2: R_(Math.PI/2,0,Math.PI/2); break
    }        
  }
  let ax = 0, ay = 0, az = 0
  facet.map(q_=>{ ax += q_[0], ay += q_[1], az += q_[2] })
  ax /= facet.length, ay /= facet.length, az /= facet.length
  let b1 = facet[2][0]-facet[1][0], b2 = facet[2][1]-facet[1][1], b3 = facet[2][2]-facet[1][2]
  let c1 = facet[1][0]-facet[0][0], c2 = facet[1][1]-facet[0][1], c3 = facet[1][2]-facet[0][2]
  let crs = [b2*c3-b3*c2,b3*c1-b1*c3,b1*c2-b2*c1]
  d = Math.hypot(...crs)+.001
  let nls = 1 //normal line length
  crs = crs.map(q=>q/d*nls)
  let X1_ = ax, Y1_ = ay, Z1_ = az
  let flip = 1
  if(autoFlipNormals){
    let d1_ = Math.hypot(X1_-X1,Y1_-Y1,Z1_-Z1)
    let d2_ = Math.hypot(X1-(ax + crs[0]/99),Y1-(ay + crs[1]/99),Z1-(az + crs[2]/99))
    flip = d2_>d1_?-1:1
  }
  let X2_ = ax + (crs[0]*=flip), Y2_ = ay + (crs[1]*=flip), Z2_ = az + (crs[2]*=flip)

  let p1_ = Math.atan2(X2_-X1_,Z2_-Z1_)
  let p2_ = -(Math.acos((Y2_-Y1_)/(Math.hypot(X2_-X1_,Y2_-Y1_,Z2_-Z1_)+.001))+Math.PI/2)
  let isc = false, iscs = [false,false,false]
  X_ = X1, Y_ = Y1, Z_ = Z1
  R_(0,-p2_,-p1_)
  let rx_ = X_, ry_ = Y_, rz_ = Z_
  for(let m=3;m--;){
    if(isc === false){
      X_ = rx_, Y_ = ry_, Z_ = rz_
      rotSwitch(m)
      X1_ = X_, Y1_ = Y_, Z1_ = Z_ = 5, X_ = X2, Y_ = Y2, Z_ = Z2
      R_(0,-p2_,-p1_)
      rotSwitch(m)
      X2_ = X_, Y2_ = Y_, Z2_ = Z_
      facet.map((q_,j_)=>{
        if(isc === false){
          let l = j_
          X_ = facet[l][0], Y_ = facet[l][1], Z_ = facet[l][2]
          R_(0,-p2_,-p1_)
          rotSwitch(m)
          let X3_=X_, Y3_=Y_, Z3_=Z_
          l = (j_+1)%facet.length
          X_ = facet[l][0], Y_ = facet[l][1], Z_ = facet[l][2]
          R_(0,-p2_,-p1_)
          rotSwitch(m)
          let X4_ = X_, Y4_ = Y_, Z4_ = Z_
          if(l_=I_(X1_,Y1_,X2_,Y2_,X3_,Y3_,X4_,Y4_)) iscs[m] = l_
        }
      })
    }
  }
  if(iscs.filter(v=>v!==false).length==3){
    let iscx = iscs[1][0], iscy = iscs[0][1], iscz = iscs[0][0]
    let pointInPoly = true
    ax=0, ay=0, az=0
    facet.map((q_, j_)=>{ ax+=q_[0], ay+=q_[1], az+=q_[2] })
    ax/=facet.length, ay/=facet.length, az/=facet.length
    X_ = ax, Y_ = ay, Z_ = az
    R_(0,-p2_,-p1_)
    X1_ = X_, Y1_ = Y_, Z1_ = Z_
    X2_ = iscx, Y2_ = iscy, Z2_ = iscz
    facet.map((q_,j_)=>{
      if(pointInPoly){
        let l = j_
        X_ = facet[l][0], Y_ = facet[l][1], Z_ = facet[l][2]
        R_(0,-p2_,-p1_)
        let X3_ = X_, Y3_ = Y_, Z3_ = Z_
        l = (j_+1)%facet.length
        X_ = facet[l][0], Y_ = facet[l][1], Z_ = facet[l][2]
        R_(0,-p2_,-p1_)
        let X4_ = X_, Y4_ = Y_, Z4_ = Z_
        if(I_(X1_,Y1_,X2_,Y2_,X3_,Y3_,X4_,Y4_)) pointInPoly = false
      }
    })
    if(pointInPoly){
      X_ = iscx, Y_ = iscy, Z_ = iscz
      R_(0,p2_,0)
      R_(0,0,p1_)
      isc = [[X_,Y_,Z_], [crs[0],crs[1],crs[2]]]
    }
  }
  return isc
}

const Reflect = (a, n) => {
  let d1 = Math.hypot(...a)+.00001
  let d2 = Math.hypot(...n)+.00001
  a[0]/=d1
  a[1]/=d1
  a[2]/=d1
  n[0]/=d2
  n[1]/=d2
  n[2]/=d2
  let dot = -a[0]*n[0] + -a[1]*n[1] + -a[2]*n[2]
  let rx = -a[0] - 2 * n[0] * dot
  let ry = -a[1] - 2 * n[1] * dot
  let rz = -a[2] - 2 * n[2] * dot
  return [-rx*d1, -ry*d1, -rz*d1]
}

const PointInPoly2D = (X, Y, poly) => {
  var ax = 0, ay = 0, ct = 0
  var X1, Y1, X2, Y2, X3, Y3, X4, Y4
  poly.map(v => {
    ax += v[0]
    ay += v[1]
    ct ++
  })
  ax /= ct
  ay /= ct
  
  X1 = X //ax
  Y1 = Y //ay
  X2 = 1E6
  Y2 = 1E6
  ct = 0
  var l
  poly.map((v, i) => {
    l = i
    X3 = poly[l][0]
    Y3 = poly[l][1]
    l = (i+1) % poly.length
    X4 = poly[l][0]
    Y4 = poly[l][1]
    if(Intersects(X1,Y1,X2,Y2,X3,Y3,X4,Y4)) ct++
  })
  return ct == 1
}

const Intersects = (A,B,M,D,E,F,G,H)=>{
  var K, J, L
  return (K=((G-E)*(B-F)-(H-F)*(A-E))/(J=(H-F)*(M-A)-(G-E)*(D-B)))>=0&&K<=1&&(L=((M-A)*(B-F)-(D-B)*(A-E))/J)>=0&&L<=1?[A+K*(M-A),B+K*(D-B)]:false
}

const Normal = (facet, autoFlipNormals=false, X1=0, Y1=0, Z1=0) => {
  var ax = 0, ay = 0, az = 0, crs, d
  facet.map(q_=>{ ax += q_[0], ay += q_[1], az += q_[2] })
  ax /= facet.length, ay /= facet.length, az /= facet.length
  var b1 = facet[2][0]-facet[1][0], b2 = facet[2][1]-facet[1][1], b3 = facet[2][2]-facet[1][2]
  var c1 = facet[1][0]-facet[0][0], c2 = facet[1][1]-facet[0][1], c3 = facet[1][2]-facet[0][2]
  crs = [b2*c3-b3*c2,b3*c1-b1*c3,b1*c2-b2*c1]
  d = Math.hypot(...crs)+.0001
  var nls = 1 //normal line length
  crs = crs.map(q=>q/d*nls)
  var X1_ = ax, Y1_ = ay, Z1_ = az
  var flip = 1
  if(autoFlipNormals){
    var d1_ = Math.hypot(X1_-X1,Y1_-Y1,Z1_-Z1)
    var d2_ = Math.hypot(X1-(ax + crs[0]/99),Y1-(ay + crs[1]/99),Z1-(az + crs[2]/99))
    flip = d2_>d1_?-1:1
  }
  var X2_ = ax + (crs[0]*=flip), Y2_ = ay + (crs[1]*=flip), Z2_ = az + (crs[2]*=flip)
  
  //return [X2_-X1_, Y2_-Y1_, Z2_-Z1_]
  return [X1_, Y1_, Z1_, X2_, Y2_, Z2_]
}


const UnloadFPSControls = async (renderer) => {
  renderer.cameraMode     = 'default'
  //renderer.crosshairMap   = ''
  renderer.showCrosshair  = false
  //renderer.crosshairSel   = 0
  renderer.useFPSControls = false
}

const LoadFPSControls = async (renderer, options) => {

  var mspeed = 1
  var rspeed = 1
  var grav   = .01
  renderer.cameraMode            = 'fps'
  renderer.crosshairMap          = ''
  renderer.showCrosshair         = true
  renderer.crosshairSize         = 1
  renderer.lastInteraction       = 0
  renderer.hasTraction           = true
  renderer.focusRequiredForMouse = true
  renderer.flyMode               = false
  renderer.useKeys               = true
  renderer.crosshairSel          = 0
  renderer.useFPSControls        = true
  var crosshairs = Array(3).fill().map((v, i) => `${ModuleBase}/resources/crosshairs/crosshair${i+1}.png`)
  if(typeof options != 'undefined'){
    Object.keys(options).forEach((key, idx) =>{
      switch(key.toLowerCase()){
        case 'mspeed': mspeed = +options[key]; break
        case 'rspeed': rspeed = +options[key]; break
        case 'grav': grav = +options[key]; break
        case 'crosshairsel': renderer.crosshairSel = +options[key]; break
        case 'crosshairsize': renderer.crosshairSize = +options[key]; break
        case 'flymode': renderer.flyMode = !!options[key]; break
        case 'usekeys': renderer.useKeys = !!options[key]; break
        case 'crosshairmap': renderer.crosshairMap = options[key]; break
        case 'showcrosshair': renderer.showCrosshair = !!options[key]; break
        case 'focusrequiredformouse': renderer.focusRequiredForMouse = !!options[key]; break
      }
    })
  }

  var crosshairsLoaded = false
  var crosshairImages  = Array(crosshairs.length).fill()
  crosshairImages.map(async (v, i) => {
    crosshairImages[i] = {
      img: new Image(),
      loaded: false,
    }
    crosshairImages[i].img.onload = () => {
      crosshairImages[i].loaded = true
    }
    await fetch(crosshairs[i]).then(res=>res.blob()).then(data => {
      crosshairImages[i].img.src = URL.createObjectURL(data)
    })
    //return ret
  })
  if(renderer.crosshairMap){
    renderer.crosshairSel = crosshairs.length
    crosshairImages.push( {img: new Image(), loaded: false} )
    crosshairImages[crosshairImages.length - 1].img.onload = () => {
      crosshairImages[crosshairImages.length - 1].loaded = true
    }
    await fetch(renderer.crosshairMap).then(res=>res.blob()).then(data => {
      crosshairImages[crosshairImages.length-1].img.src = URL.createObjectURL(data)
    })
  }

  if(renderer.useKeys){
    var mx, my
    var mv = .1 * mspeed
    var rv = .005 * rspeed
    var rvx = 0
    var rvy = 0
    var px  = 0
    var py  = 0
    var pz  = 0
    var pvx = 0
    var pvy = 0
    var pvz = 0
    var accel = 1
    var rdrag = 1.66
    var pdrag = 1.2
    var mbutton = false
    renderer.keys = Array(256).fill().map((v, i) => false)
    renderer.keyTimers = Array(256).fill(0)
    renderer.keyTimerInterval = .2
    
    window.addEventListener('keydown', e => {
      if(document.activeElement.nodeName == 'CANVAS'){
        renderer.keys[e.keyCode] = true
        renderer.lastInteraction = renderer.t
      }
    })
    window.addEventListener('keyup', e => {
      if(document.activeElement.nodeName == 'CANVAS'){
        renderer.keys[e.keyCode] = false
        renderer.lastInteraction = renderer.t
      }
    })
    window.addEventListener('mousedown', e => {
      renderer.lastInteraction = renderer.t
      if(e.button === 0) {
        mbutton = true
        //jump()
        //renderer.c.requestFullscreen()
        var el = document.querySelectorAll('.genericPopup')
        if(!el.length && document.activeElement.nodeName == 'CANVAS') renderer.c.requestPointerLock({unadjustedMovement: true})
      }
    })
    window.addEventListener('mouseup', e => {
      renderer.lastInteraction = renderer.t
      if(e.button === 0) mbutton = false
    })
    window.addEventListener('mousemove', e => {
      renderer.lastInteraction = renderer.t
      if(document.pointerLockElement == renderer.c || !renderer.focusRequiredForMouse){
        var rect = renderer.c.getBoundingClientRect()
        mx = (e.pageX - rect.left) / renderer.c.clientWidth * renderer.c.width
        my = (e.pageY - rect.top) / renderer.c.clientHeight* renderer.c.height
        rvx -= e.movementX * rv
        rvy += e.movementY * rv
      }
    })
    
    renderer.doKeys = async () => {
      if(renderer.showCrosshair && crosshairImages[renderer.crosshairSel].loaded) {
        var s = 200 * renderer.crosshairSize
        Overlay.ctx.globalAlpha = .6
        Overlay.ctx.drawImage(crosshairImages[renderer.crosshairSel].img,
          Overlay.width / 2 - s/2, Overlay.height / 2 - s/2, s, s)
        Overlay.ctx.globalAlpha = 1
      }
    
      renderer.yaw += rvx
      renderer.pitch += rvy
      renderer.pitch = Math.min(Math.PI/2, Math.max(-Math.PI/2, renderer.pitch))
      rvx /= rdrag
      rvy /= rdrag
      
      renderer.x += pvx
      renderer.y += pvy
      renderer.z += pvz
      if(document.activeElement.nodeName == 'CANVAS' && (renderer.hasTraction || renderer.flyMode)){
        pvx /= pdrag
        pvy /= pdrag
        pvz /= pdrag
      }
      
      if(renderer.flyMode && document.activeElement.nodeName == 'CANVAS'){
        var p1 = -renderer.yaw + Math.PI
        var p2 = renderer.pitch
        switch(renderer.mouseButton){
          case 1:
            pvx -= S(p1) * S(p2) * mv * accel
            pvy += C(p2) * mv * accel
            pvz -= C(p1) * S(p2) * mv * accel
          break
          case 2:
            pvx += S(p1) * S(p2) * mv * accel
            pvy -= C(p2) * mv * accel
            pvz += C(p1) * S(p2) * mv * accel
          break
          default:
          break
        }
      }
      
      accel = 1
      if(document.activeElement.nodeName == 'CANVAS' &&
        (renderer.hasTraction || renderer.flyMode)) renderer.keys.map((v, i) => {
        if(renderer.keys[i]){
          switch(i){
            case 16:  // shift
              accel = 3
            break
            case 37:  // left arrow
              rvx += rv * 12
            break
            case 38:  // up arrow
              rvy -= rv * 12
            break
            case 39:  // right arrow
              rvx -= rv * 12
            break
            case 40:  // down arrow
              rvy += rv * 12
            break
            case 87:  //w
              var p1 = -renderer.yaw + Math.PI
              var p2 = renderer.pitch + Math.PI / 2
              if(renderer.flyMode){
                pvx += S(p1) * S(p2) * mv * accel
                pvy -= C(p2) * mv * accel
                pvz += C(p1) * S(p2) * mv * accel
              }else{
                pvx += S(p1) * mv * accel
                pvz += C(p1) * mv * accel
              }
            break
            case 65:  //a
              var p1 = -renderer.yaw + Math.PI / 2
              var p2 = renderer.pitch + Math.PI / 2
              if(renderer.flyMode){
                pvx += S(p1) * mv * accel
                pvz += C(p1) * mv * accel
              }else{
                pvx += S(p1) * mv * accel
                pvz += C(p1) * mv * accel
              }
            break
            case 83:  //s
              var p1 = -renderer.yaw + Math.PI
              var p2 = renderer.pitch + Math.PI / 2
              if(renderer.flyMode){
                pvx -= S(p1) * S(p2) * mv * accel
                pvy += C(p2) * mv * accel
                pvz -= C(p1) * S(p2) * mv * accel
              }else{
                pvx -= S(p1) * mv * accel
                pvz -= C(p1) * mv * accel
              }
            break
            case 68:  //d
              var p1 = -renderer.yaw + Math.PI / 2
              var p2 = renderer.pitch + Math.PI / 2
              if(renderer.flyMode){
                pvx += -S(p1) * mv * accel
                pvz += -C(p1) * mv * accel
              }else{
                pvx += -S(p1) * mv * accel
                pvz += -C(p1) * mv * accel
              }
            break
            case 32:  //space
            break
          }
        }
      })
    }
  }
}


const AnimationLoop = (renderer, func) => {
  const loop = async () => {
    Overlay.margin = renderer.margin
    Overlay.rsz()
    Overlay.width = renderer.width
    Overlay.height = renderer.height
    Overlay.c.width = renderer.c.width
    Overlay.c.height = renderer.c.height
    
    if(renderer.ready && typeof window[func] != 'undefined') await window[func]()
      
    // mimic shader rotation function, for z-sorting.
    // transparent objects must be drawn in reverse depth order

    if(renderer.particleQueue.length){
      var forSort = new Float32Array()
      var vec
      
      renderer.ctx.blendFunc(renderer.ctx.SRC_ALPHA, renderer.ctx.ONE);
      renderer.ctx.enable(renderer.ctx.BLEND)
      
      renderer.particleQueue.map((v, i) => {
        var X = v.x + renderer.x
        var Y = v.y + renderer.y
        var Z = v.z + renderer.z
        vec = R(X,Y,Z, {roll: renderer.roll,
                        pitch: renderer.pitch,
                        yaw: renderer.yaw}, false)
                        
        var camz = renderer.z / 1e3 *
                     Math.pow(5.0, (Math.log(renderer.fov) / 1.609438))
        forSort = [...forSort, {idx: i, z: camz + vec[2]}]
      })
      forSort.sort((a, b) => b.z - a.z)
      renderer.particleQueue.map(async (alphaShape, idx) => {

        var shape = renderer.particleQueue[forSort[idx].idx]
        
        var shouldDisableDepth = () => {
          return false //shape.isLight || shape.isSprite || shape.disableDepthTest
        }
        
        if(shouldDisableDepth()) renderer.ctx.disable(renderer.ctx.DEPTH_TEST)
    
        var penumbra = shape.penumbra
        for(var m = 1 + (shape.isParticle && penumbra ? 1 : 0); m--;){
          await renderer.Draw(shape, true, shape.isParticle && penumbra && !m)
        }
        if(shouldDisableDepth()) renderer.ctx.enable(renderer.ctx.DEPTH_TEST)
      })
    
      // disable alpha
      renderer.ctx.blendFunc(renderer.ctx.ONE, renderer.ctx.ZERO)
      renderer.ctx.disable(renderer.ctx.BLEND)
    }
    
    if(renderer.alphaQueue.length){
      var forSort = new Float32Array()
      var vec
      
      renderer.ctx.blendFunc(renderer.ctx.SRC_ALPHA, renderer.ctx.ONE);
      renderer.ctx.enable(renderer.ctx.BLEND)
      
      renderer.alphaQueue.map((v, i) => {
        var X = v.x + renderer.x
        var Y = v.y + renderer.y
        var Z = v.z + renderer.z
        vec = R(X,Y,Z, {roll: renderer.roll,
                        pitch: renderer.pitch,
                        yaw: renderer.yaw}, false)
                        
        var camz = renderer.z / 1e3 *
                     Math.pow(5.0, (Math.log(renderer.fov) / 1.609438))
        forSort = [...forSort, {idx: i, z: camz + vec[2]}]
      })
      forSort.sort((a, b) => b.z - a.z)
      renderer.alphaQueue.map(async (alphaShape, idx) => {

        var shape = renderer.alphaQueue[forSort[idx].idx]
        
        var shouldDisableDepth = () => {
          return false //shape.isLight || shape.isSprite ||shape.disableDepthTest
        }
        
        if(shouldDisableDepth()) renderer.ctx.disable(renderer.ctx.DEPTH_TEST)
    
        var penumbra = shape.penumbra
        for(var m = 1 + (shape.isParticle && penumbra ? 1 : 0); m--;){
          await renderer.Draw(shape, true, shape.isParticle && penumbra && !m)
        }
        if(shouldDisableDepth()) renderer.ctx.enable(renderer.ctx.DEPTH_TEST)
      })
    
      // disable alpha
      renderer.ctx.blendFunc(renderer.ctx.ONE, renderer.ctx.ZERO)
      renderer.ctx.disable(renderer.ctx.BLEND)
    }

    
    renderer.t += 1/60 //performance.now() / 1000
    //if(renderer.active) requestAnimationFrame(loop)
    requestAnimationFrame(loop)
    renderer.alphaQueue = new Float32Array()
    renderer.particleQueue = new Float32Array()
    
    if(renderer.useKeys && renderer.doKeys){
      await renderer.doKeys()
    }
  }
  window.addEventListener('load', () => {
    renderer.ready = true
    loop()
  })
}

const RGBToHSV = (R, G, B) => HSVFromRGB(R, G, B)

const HSVFromRGB = (R, G, B) => {
  let R_=R/255
  let G_=G/255
  let B_=B/255
  let Cmin = Math.min(R_,G_,B_)
  let Cmax = Math.max(R_,G_,B_)
  let val = Cmax //(Cmax+Cmin) / 2
  let delta = Cmax-Cmin
  let sat = Cmax ? delta / Cmax: 0
  let min=Math.min(R,G,B)
  let max=Math.max(R,G,B)
  let hue = 0
  if(delta){
    if(R>=G && R>=B) hue = (G-B)/(max-min)
    if(G>=R && G>=B) hue = 2+(B-R)/(max-min)
    if(B>=G && B>=R) hue = 4+(R-G)/(max-min)
  }
  hue*=60
  while(hue<0) hue+=360;
  while(hue>=360) hue-=360;
  return [hue, sat, val]
}

const HSVToHex = (H, S, V) => HexFromHSV(H, S, V)

const HexFromHSV = (H, S, V) => {
  let ret = RGBFromHSV(H, S, V)
  return RGBToHex(...ret)
}

const HSVToRGB = (H, S, V) => RGBFromHSV(H, S, V)

const RGBFromHSV = (H, S, V) => {
  while(H<0) H+=360;
  while(H>=360) H-=360;
  let C = V*S
  let X = C * (1-Math.abs((H/60)%2-1))
  let m = V-C
  let R_, G_, B_
  if(H>=0 && H < 60)    R_=C, G_=X, B_=0
  if(H>=60 && H < 120)  R_=X, G_=C, B_=0
  if(H>=120 && H < 180) R_=0, G_=C, B_=X
  if(H>=180 && H < 240) R_=0, G_=X, B_=C
  if(H>=240 && H < 300) R_=X, G_=0, B_=C
  if(H>=300 && H < 360) R_=C, G_=0, B_=X
  let R = (R_+m)*255
  let G = (G_+m)*255
  let B = (B_+m)*255
  return [R,G,B]
}

const HexFromRGB = (R, G, B) => RGBToHex(R, G, B)

const RGBToHex = (R, G, B) => {
  let a = '0123456789abcdef'
  let ret = ''
  ret += a[R/16|0]
  ret += a[R-(R/16|0)*16|0]
  ret += a[G/16|0]
  ret += a[G-(G/16|0)*16|0]
  ret += a[B/16|0]
  ret += a[B-(B/16|0)*16|0]
  return Number('0x' + ret)
}

const RGBFromHex = val => HexToRGB(val)
const HexToRGB = val => {
    var b = ((val/256) - (val/256|0)) //* 256|0
    var g = ((val/256**2) - (val/256**2|0)) //* 256|0
    var r = ((val/256**3) - (val/256**3|0)) //* 256|0
    return [r, g, b]
}


const getParams = ctx => {
  var paramNames = ['COPY_READ_BUFFER_BINDING','COPY_WRITE_BUFFER_BINDING','DRAW_BUFFERi','DRAW_FRAMEBUFFER_BINDING','FRAGMENT_SHADER_DERIVATIVE_HINT','MAX_3D_TEXTURE_SIZE','MAX_ARRAY_TEXTURE_LAYERS','MAX_CLIENT_WAIT_TIMEOUT_WEBGL','MAX_COLOR_ATTACHMENTS','MAX_COMBINED_FRAGMENT_UNIFORM_COMPONENTS','MAX_COMBINED_UNIFORM_BLOCKS','MAX_COMBINED_VERTEX_UNIFORM_COMPONENTS','MAX_DRAW_BUFFERS','MAX_ELEMENT_INDEX','MAX_ELEMENTS_INDICES','MAX_ELEMENTS_VERTICES','MAX_FRAGMENT_INPUT_COMPONENTS','MAX_FRAGMENT_UNIFORM_BLOCKS','MAX_FRAGMENT_UNIFORM_COMPONENTS','MAX_PROGRAM_TEXEL_OFFSET','MAX_SAMPLES','MAX_SERVER_WAIT_TIMEOUT','MAX_TEXTURE_LOD_BIAS','MAX_TRANSFORM_FEEDBACK_INTERLEAVED_COMPONENTS','MAX_TRANSFORM_FEEDBACK_SEPARATE_ATTRIBS','MAX_TRANSFORM_FEEDBACK_SEPARATE_COMPONENTS','MAX_UNIFORM_BLOCK_SIZE','MAX_UNIFORM_BUFFER_BINDINGS','MAX_VARYING_COMPONENTS','MAX_VERTEX_OUTPUT_COMPONENTS','MAX_VERTEX_UNIFORM_BLOCKS','MAX_VERTEX_UNIFORM_COMPONENTS','MIN_PROGRAM_TEXEL_OFFSET','PACK_ROW_LENGTH','PACK_SKIP_PIXELS','PACK_SKIP_ROWS','PIXEL_PACK_BUFFER_BINDING','PIXEL_UNPACK_BUFFER_BINDING','RASTERIZER_DISCARD','READ_BUFFER','READ_FRAMEBUFFER_BINDING','SAMPLE_ALPHA_TO_COVERAGE','SAMPLE_COVERAGE','SAMPLER_BINDING','TEXTURE_BINDING_2D_ARRAY','TEXTURE_BINDING_3D','TRANSFORM_FEEDBACK_ACTIVE','TRANSFORM_FEEDBACK_BINDING','TRANSFORM_FEEDBACK_BUFFER_BINDING','TRANSFORM_FEEDBACK_PAUSED','UNIFORM_BUFFER_BINDING','UNIFORM_BUFFER_OFFSET_ALIGNMENT','UNPACK_IMAGE_HEIGHT','UNPACK_ROW_LENGTH','UNPACK_SKIP_IMAGES','UNPACK_SKIP_PIXELS','UNPACK_SKIP_ROWS','VERTEX_ARRAY_BINDING']
  
  var params = []
  paramNames.map(name => {
    params.push({ name, val: ctx.getParameter(ctx[name]) })
  })
  var popup = document.createElement('div')
  popup.style.position = 'fixed'
  popup.style.zIndex = 100000
  popup.style.left = '50%'
  popup.style.top = '50%'
  popup.style.transform = 'translate(-50%, -50%)'
  popup.style.background = '#0008'
  popup.style.padding = '20px'
  popup.style.width = '600px'
  popup.style.height = '350px'
  popup.style.border = '1px solid #fff4'
  popup.style.borderRadius = '5px'
  popup.style.fontFamily = 'monospace'
  popup.style.fontSize = '20px'
  popup.style.color = '#fff'
  var titleEl = document.createElement('div')
  titleEl.style.fontSize = '24px'
  titleEl.style.color = '#0f8c'
  titleEl.innerHTML = `rendering context parameters` + '<br><br>'
  popup.appendChild(titleEl)
  var output = document.createElement('div')
  //output.id = 'shapeDataOutput' + geometry.name + geometry.shapeType
  output.style.minWidth = '100%'
  output.style.height = '250px'
  output.style.background = '#333'
  output.style.border = '1px solid #fff4'
  output.style.overflowY = 'auto'
  output.style.wordWrap = 'break-word'
  output.style.color = '#888'
  output.style.fontSize = '10px'
  popup.appendChild(output)
  var copyButton = document.createElement('button')
  copyButton.style.border = 'none'
  copyButton.style.padding = '3px'
  copyButton.style.cursor = 'pointer'
  copyButton.fontSize = '20px'
  copyButton.style.borderRadius = '10px'
  copyButton.style.margin = '10px'
  copyButton.style.minWidth = '100px'
  copyButton.innerHTML = '📋 copy'
  copyButton.title = "copy shape data to clipboard"
  copyButton.onclick = () => {
    var range = document.createRange()
    range.selectNode(output)
    window.getSelection().removeAllRanges()
    window.getSelection().addRange(range)
    document.execCommand("copy")
    window.getSelection().removeAllRanges()
    copyButton.innerHTML = 'COPIED!'
    setTimeout(() => {
      copyButton.innerHTML = '📋 copy'
    } , 1000)
  }
  popup.appendChild(copyButton)
  var closeButton = document.createElement('button')
  closeButton.onclick = () => popup.remove()
  
  closeButton.style.border = 'none'
  closeButton.style.padding = '3px'
  closeButton.style.cursor = 'pointer'
  closeButton.fontSize = '20px'
  closeButton.style.borderRadius = '10px'
  closeButton.style.margin = '10px'
  closeButton.style.background = '#faa'
  closeButton.style.minWidth = '100px'
  closeButton.innerHTML = 'close'
  popup.appendChild(closeButton)
  
  output.innerHTML = JSON.stringify(params)
  document.body.appendChild(popup)
}

var Overlay        // for sketch-up, e.g. shape-bounding graphics
Overlay = await Renderer({ context: { mode: '2d', margin: 0 } })
Overlay.c.style.background = '#0000'
Overlay.c.style.zIndex = 10

var scratchHeightMap = document.createElement('canvas')
var SHMctx = scratchHeightMap.getContext('2d', {willReadFrequently: true})
var SHMdata

export {
  Renderer,
  LoadGeometry,
  BasicShader,
  DestroyRenderer,
  ResizeRenderer,
  DestroyShape,
  AnimationLoop,
  Tetrahedron,
  Cube,
  Octahedron,
  Icosahedron,
  Dodecahedron,
  Cylinder,
  Torus,
  TorusKnot,
  Rectangle,
  Q, R, R_ypr, R_pyr, R_rpy,
  SyncNormals,
  Intersects,
  PointInPoly2D,
  PointInPoly3D,
  ShowBounding,
  GetShaderCoord,
  Reflect,
  Normal,
  ImageToPo2,
  LoadOBJ,
  IsPowerOf2,
  RGBToHSV,
  HSVToHex,
  HexFromHSV,
  HSVToRGB,
  RGBFromHSV,
  HexFromRGB,
  RGBToHex,
  RGBFromHex,
  HexToRGB,
  GeoSphere,
  ModuleBase,
  LoadFPSControls,
  Overlay,
}


FILE;
file_put_contents('../../flock/coordinates.js', $file);
?>