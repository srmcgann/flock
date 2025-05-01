<?
$file = <<<FILE
<!DOCTYPE html>
<html>
  <head>
    <style>
      body, html{
        background: #000;
        margin: 0;
        min-height: 100vh;
        overflow: hidden;
      }
      .overlay{
        width: 100vw;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        opacity: .7;
        z-index: 10000;
      }
      .overlayContent{
        object-fit: contain;
        width: 100vw;
        height: 100vh;
      }
      #playerName:focus{
        outline: none;
      }
      #playerName{
        background: #024;
        color: #8fc;
        width: 300px;
        border: 1px solid #3333;
        font-size: 16px;
        font-family: verdana;
        border-radius: 5px;
        cursor: pointer;
      }
      .inputLabel{
        cursor: pointer;
        font-family: verdana;
        z-index: 100;
        font-size: 16px;
        position: fixed;
        top: 5px;
        left: 5px;
        opacity: .75;
        color: #fff;
      }
    </style>
  </head>
  <body>
    <label for="playerName" class="inputLabel">
      name 
      <input
        type="text"
        placeholder="enter a name"
        id="playerName"
        oninput="updatePlayerName(event)"
        onmousedown="selectMaybe(this)"
      />
    </label>
    <div class="overlay">
      <video
        id="loadingVideo"
        class="overlayContent"
        loop autoplay muted
        src="https://boss.mindhackers.org/remapper/loading.mp4"
      ></video>
    </div>
    <script type="module">
    
      // net-game boilerplate
      var X, Y, Z, roll, pitch, yaw
      var reconnectionAttempts = 0
      var lerpFactor = 20
      var players    = []
      var iplayers   = []  // interpolated local mirror
      ///////////////////////
      

      // game guts
      
      import * as Coordinates from
      "./coordinates.js"
      
      var S = Math.sin
      var C = Math.cos
      var Rn = Math.random
    
      const floor = (X, Z) => {
        //var d = Math.hypot(X, Z) / 500
        return (S(X/250) * S(Z/250)) * 200
      }
      var X, Y, Z
      var cl = 12
      var rw = 1
      var br = 12
      var sp = 8
      var tx, ty, tz
      var ls = 2**.5 / 2 * sp, p, a
      var texCoords = []
      var minX = 6e6, maxX = -6e6
      var minZ = 6e6, maxZ = -6e6
      var mag = 12.5 //20 * (2**.5/2)
      var ax, ay, az, nax, nay, naz
      var gunShape, missileShape, bulletShape
      var muzzleFlair, chaingunShape
      var muzzleFlairBase, thrusterShape
      var sparksShape, splosionShape
      var bulletParticles


      var refTexture = 'https://srmcgann.github.io/Coordinates/resources/nebugrid_po2.jpg'
      var heightMap = 'https://srmcgann.github.io/Coordinates/resources/bumpmap_equirectangular_po2.jpg'
      var floorMap = 'https://srmcgann.github.io/Coordinates/resources/grid_saphire_dark_po2_lowres.jpg'
    
      var rendererOptions = {
        ambientLight: .2,
        width: 960,
        height: 540,
        margin: 0,
        fov: 1600
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 10
      
      Coordinates.AnimationLoop(renderer, 'Draw')

      var grav = .25
      var playervy = 0
      renderer.c.onmousedown = e => {
        if(document.activeElement.nodeName == 'CANVAS' && (!renderer.flyMode &&
           renderer.hasTraction) && e.button == 2){
          playervy -= 20
        }
      }

      var shapes       = []
      var missiles     = []
      var bullets      = []
      var flashes      = []
      var splosions    = []
      var sparks       = []
      var baseSplosion = []
      var baseSparks   = []

      var launch = async (width, height) => {
        var ar = width / height
        width = Math.min(1e3, width)
        height = width / ar
        await Coordinates.ResizeRenderer(renderer, width, height)
        renderer.fov = Math.hypot(width, height) / 2
        //renderer.optionalPlugins[0].enabled = plugin

        var shaderOptions = [
          {lighting: { type: 'ambientLight', value: .2}},
          { uniform: {
            type: 'phong',
            value: .35
          } },
          { uniform: {
            type: 'reflection',
            playbackSpeed: 2,
            enabled: true,
            map: refTexture,
            value: .1
          } },
        ]
        var shader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          {lighting: { type: 'ambientLight', value: .5}},
          { uniform: {
            type: 'phong',
            value: 0
          } },
          { uniform: {
            type: 'reflection',
            map: refTexture,
            enabled: false,
            value: .2,
          } },
        ]
        var projectileShader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          {lighting: { type: 'ambientLight', value: .2}},
          { uniform: {
            type: 'phong',
            value: 0
          } },
          { uniform: {
            type: 'reflection',
            enabled: false,
            map: refTexture,
            value: .25
          } },
        ]
        var floorShader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          { lighting: {type: 'ambientLight', value: .2},
          },
          { uniform: {
            type: 'phong',
            value: 0
          } }
        ]
        var backgroundShader = await Coordinates.BasicShader(renderer, shaderOptions)


        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.png',
          name: 'bird ship',
          size: 1,
          rotationMode: 1,
          colorMix: 0,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
          await shader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'sprite',
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/megastar.png',
          name: 'muzzle flair',
          size: 1,
          rotationMode: 1,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            muzzleFlair = geometry
          })
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            muzzleFlairBase = geometry
          })
        }
        var geoOptions = {
          shapeType: 'sprite',
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star1.png',
          name: 'thruster',
          size: 5,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            thrusterShape = geometry
          })
        }

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/guns.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.png',
          name: 'gun shape',
          size: 1,
          rotationMode: 1,
          colorMix: 0,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          gunShape = geometry
          await shader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/chainguns.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.png',
          name: 'chainguns',
          size: 1,
          rotationMode: 1,
          colorMix: 0,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          chaingunShape = geometry
          await shader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/missile.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.png',
          name: 'missile',
          rotationMode: 1,
          colorMix: 0,
          size: 1,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          missileShape = geometry
          await projectileShader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/bullet.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/bird ship/birdship.png',
          name: 'bullet',
          rotationMode: 1,
          colorMix: 0,
          size: 1,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          bulletShape = geometry
          await projectileShader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/arrows/arrow 1.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/arrows/arrow 1b.jpg',
          name: 'arrow 1',
          rotationMode: 1,
          colorMix: 0,
          size: 1,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
          await shader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/arrows/arrow 2.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/arrows/arrow 2b.jpg',
          name: 'arrow 2',
          rotationMode: 1,
          colorMix: 0,
          size: 1,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
          await shader.ConnectGeometry(geometry)
        })


        var geoOptions = {
          shapeType: 'dodecahedron',
          name: 'background',
          subs: 2,
          size: 1e4,
          colorMix: 0,
          playbackSpeed: 1,
          map: refTexture,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
          await backgroundShader.ConnectGeometry(geometry)
        }) 

        
        geometryData = Array(cl*rw*br).fill().map((v, i) => {
          tx = ((i%cl) - cl/2 + .5) * sp
          ty = 0
          tz = ((i/cl/rw|0) - br/2 + .5) * sp
          a = []
          for(var j = 4; j--;){
            X = tx + S(p = Math.PI*2/4*j + Math.PI/4) * ls
            Z = tz + C(p) * ls
            Y = ty + floor(X, Z)
            a = [...a, [X, Y, Z]]
            if(X < minX) minX = X
            if(X > maxX) maxX = X
            if(Z < minZ) minZ = Z
            if(Z > maxZ) maxZ = Z
          }
          return a
        })
        
        var rangeX = maxX - minX
        var rangeZ = maxZ - minZ
        geometryData.map(face => {
          var a = []
          face.map(q=>{
            var uvx = (q[0] - minX) / rangeX
            var uvz = (q[2] - minZ) / rangeZ
            a = [...a, [uvx, uvz]]
          })
          texCoords = [...texCoords, a]
        })
        
        var geoOptions = {
          shapeType: 'dynamic',
          name: 'floor',
          equirectangular: false,
          size: 5,
          //averageNormals: true, 
          geometryData,
          scaleUVX: 1,
          scaleUVY: 1,
          texCoords,
          color: 0xffffff,
          colorMix: 0,
          fipNormals: true,
          //pitch: Math.PI,
          map: floorMap,
          //heightMap,
          //heightMapIntensity: 50,
          playbackSpeed: 1
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          Coordinates.SyncNormals(geometry, true, true)
          shapes.push(geometry)
          await floorShader.ConnectGeometry(geometry)
        })

        
        var geoOptions = {
          shapeType: 'point light',
          name: 'point light',
          showSource: true,
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star0.png',
          size: 25,
          lum: 200,
          color: 0xffffff,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })  

        var iPc = 500
        var G   = cl * sp * mag * 2
        var geometryData = Array(iPc).fill().map(v=>{
          X = (Rn()-.5) * G
          Y = (Rn()-.5) * G
          Z = (Rn()-.5) * G
          return [X, Y, Z]
        })
        var geoOptions = {
          shapeType: 'particles',
          name: 'particles',
          geometryData,
          size: 10,
          alpha: .3,
          penumbra: .25,
          color: 0xffffff,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })  
        
        var geometryData = Array(1e3).fill().map(v=> [1e6, 1e6, 1e6])
        var geoOptions = {
          shapeType: 'particles',
          name: 'bullet particles',
          geometryData,
          size: 10,
          alpha: 1,
          penumbra: .5,
          color: 0x44ffcc,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          bulletParticles = geometry
        })  

        var iPc  = 1e3
        var iPv  = 10
        var geometryData = Array(iPc).fill().map(v=>{
          var vel = Rn() * .1 * iPv + iPv * .9
          var p, q, d
          var vx = S(p=Math.PI*2*Rn()) *
                       S(q=Rn() < .5 ? Math.PI/2*Rn()**.5 : Math.PI - Math.PI/2*Rn()**.5)* vel
          var vy = C(q) * vel
          var vz = C(p) * S(q) * vel
          baseSplosion = [...baseSplosion, [vx, vy, vz, vx, vy, vz]]
          return [vx, vy, vz]
        })
        var geoOptions = {
          shapeType: 'particles',
          name: 'splosion particles',
          geometryData,
          x: 0, y: 0, z: 0,
          size: 25,
          alpha: .75,
          penumbra: .25,
          color: 0xffaa22,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          splosionShape = geometry
        })
        
        var iPc  = 50
        var iPv  = 6
        var geometryData = Array(iPc).fill().map(v=>{
          var vel = Rn() * .75 * iPv + iPv * .25
          var p, q, d
          var vx = S(p=Math.PI*2*Rn()) *
                       S(q=Rn() < .5 ? Math.PI/2*Rn()**.5 : Math.PI - Math.PI/2*Rn()**.5)* vel
          var vy = C(q) * vel
          var vz = C(p) * S(q) * vel
          baseSparks = [...baseSparks, [vx, vy, vz, vx, vy, vz]]
          return [vx, vy, vz]
        })
        var geoOptions = {
          shapeType: 'particles',
          name: 'spark particles',
          geometryData,
          x: 0, y: 0, z: 0,
          size: 10,
          alpha: .75,
          penumbra: .25,
          color: 0xff4400,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          sparksShape = geometry
        })  

        Coordinates.LoadFPSControls(renderer, {
          mSpeed: 10,
          flyMode: true,
          crosshairMap: 'https://boss.mindhackers.org/assets/uploads/1rvQ0b.webp',
          crosshairSel: 0,
          crosshairSize: .25
        })

        window.onkeydown = e => {
          if(document.activeElement.nodeName == 'CANVAS'){
            if(e.keyCode == 70){
              renderer.flyMode = !renderer.flyMode
            }
          }
        }

        document.querySelectorAll('.overlay').forEach(e => e.style.display = 'none')
        loadingVideo.pause()
      }


      var ctx = Coordinates.Overlay.ctx
      
      const strokeCustom = (fill = false) => {
        ctx.globalAlpha = .1
        ctx.lineWidth = 6
        ctx.stroke()
        ctx.globalAlpha = .3
        ctx.lineWidth = 1
        ctx.stroke()
        if(fill) ctx.fill()
      }

      const drawPlayerNames = shape => {
        var pt = Coordinates.GetShaderCoord(0,0,0, shape, renderer)
        var rad = 50
        ctx.lineJoin = ctx.lineCap = 'round'
        ctx.strokeStyle = '#f00'
        ctx.fillStyle = '#f022'
        ctx.beginPath()
        ctx.arc(pt[0], pt[1],rad,0,7)
        strokeCustom(true)
        
        var lx, ly
        ctx.beginPath()
        if(pt[0] > Coordinates.Overlay.c.width/2){
          lx = -1
          ctx.textAlign = 'right'
        }else{
          lx = 1
          ctx.textAlign = 'left'
        }
        if(pt[1] > Coordinates.Overlay.c.height/2){
          ly = -1
        }else{
          ly = 1
        }
        var d = Math.hypot(lx, ly)
        ctx.lineTo(pt[0]+lx/d*rad, pt[1]+ly/d*rad)
        ctx.lineTo(pt[0]+lx/d*rad*3, pt[1]+ly/d*rad*2.2)
        ctx.lineTo(pt[0]+lx/d*rad*6, pt[1]+ly/d*rad*2.2)
        strokeCustom()
        
        ctx.globalAlpha = .8
        var fontsize = rad / 3
        ctx.font = fontsize+'px verdana'
        lx = pt[0]+lx/d*rad*3.25
        ly = pt[1]+ly/d*rad*2.2
        ctx.lineWidth = 6
        ctx.globalAlpha = 1
        ctx.fillStyle = '#6fc'
        ctx.strokeStyle = '#000d'
        ctx.strokeText(shape.name, lx, ly-fontsize/3)
        ctx.fillText(shape.name, lx, ly-fontsize/3)
      }
      
      const spawnSplosion = (x, y, z) => {
        spawnFlash(x, y, z, 5)
        splosions = [...splosions, {x, y, z, data: structuredClone(baseSplosion), age: 1}]
      }
      
      const spawnSparks = (x, y, z) => {
        sparks = [...sparks, {x, y, z, data: structuredClone(baseSparks), age: 1}]
      }
      
      const spawnFlash = (x, y, z, age = 1) => {
        flashes = [...flashes, { x, y, z, age }]
      }
      
      var missileShotTimer         = 0
      var missileShotTimerInterval = .25
      var missileSpeed             = 40
      var missileLife              = 2
      const fireMissile = player => {
        var x, y, z, roll, pitch, yaw
        if(player.interpolated){
          player = player.player
          x      = -player.ix
          y      = player.iy
          z      = player.iz
          roll   = player.iroll
          pitch  = player.ipitch
          yaw    = player.iyaw
        }else{
          x      = player.x
          y      = player.y
          z      = player.z
          roll   = player.roll
          pitch  = player.pitch
          yaw    = player.yaw
        }
        
        if(renderer.t - player.missileShotTimer < missileShotTimerInterval) return
        player.missileShotTimer = renderer.t
        var p1 = yaw + Math.PI
        var p2 = -pitch + Math.PI / 2
        var vx = -S(p1) * S(p2) * missileSpeed
        var vy = C(p2) * missileSpeed
        var vz = -C(p1) * S(p2) * missileSpeed
        player.missileAlt = !player.missileAlt
        
        var offset = Coordinates.R_pyr(35 * (player.missileAlt ? -1: 1), -10, 0, player)
        if(+player.id != +playerData.id) spawnFlash(-x + offset[0],
                                                     y + offset[1],
                                                     -z + offset[2], .5)
        
        offset = Coordinates.R_pyr(35 * (player.missileAlt ? -1: 1), 0, 0, player)
        missiles = [...missiles, {
          x: -x + offset[0],
          y: y + offset[1],
          z: -z + offset[2],
          roll, pitch, yaw,
          t: renderer.t,
          id: player.id,
          vx, vy, vz,
        }]
        //coms('sync.php', 'syncPlayers')
      }

      var chaingunShotTimer         = 0
      var chaingunShotTimerInterval = .02
      var chaingunSpeed             = 60
      var chaingunLife              = 1
      const fireChainguns = player => {
        var x, y, z, roll, pitch, yaw
        if(player.interpolated){
          player = player.player
          x      = -player.ix
          y      = player.iy
          z      = player.iz
          roll   = player.iroll
          pitch  = player.ipitch
          yaw    = player.iyaw
        }else{
          x      = player.x
          y      = player.y
          z      = player.z
          roll   = player.roll
          pitch  = player.pitch
          yaw    = player.yaw
        }
        
        if(renderer.t - player.chaingunShotTimer < chaingunShotTimerInterval) return
        player.chaingunShotTimer = renderer.t
        var p1 = yaw + Math.PI
        var p2 = -pitch + Math.PI / 2
        var vx = -S(p1) * S(p2) * chaingunSpeed
        var vy = C(p2) * chaingunSpeed
        var vz = -C(p1) * S(p2) * chaingunSpeed
        player.chaingunAlt = !player.chaingunAlt
        
        var offset = Coordinates.R_pyr(20 * (player.chaingunAlt ? -1: 1), -10, 0, player)
        if(+player.id != +playerData.id) spawnFlash(-x + offset[0],
                                                     y + offset[1],
                                                    -z + offset[2], .25)
        
        offset = Coordinates.R_pyr(20 * (player.chaingunAlt ? -1: 1), 0, 0, player)
        bullets = [...bullets, {
          x: -x + offset[0],
          y: y + offset[1],
          z: -z + offset[2],
          roll, pitch, yaw,
          t: renderer.t,
          id: player.id,
          vx, vy, vz,
        }]
        //coms('sync.php', 'syncPlayers')
      }

      window.Draw = async () => {
        var t = renderer.t
        gameSync()
        
        playerData.firingMissiles  = false
        playerData.firingChainguns = false
        
        if(document.activeElement.nodeName == 'CANVAS'){
          renderer.keys.map((v, i) =>{
            if(v) {
              switch(i){
                case 90:
                  playerData.firingMissiles = true
                  fireMissile(playerData)
                break
                case 88:
                  playerData.firingChainguns = true
                  fireChainguns(playerData)
                break
              }
            }
          })
        }
        
        var fl = -floor(-renderer.x, -renderer.z) - 50
        if(renderer.flyMode){
          if(renderer.y >= fl){
            renderer.y = fl
            playervy = 0
          }
        }else{
          playervy += grav
          renderer.y += playervy
          if(renderer.y > fl - 3){
            renderer.y = fl
            playervy = 0
            renderer.hasTraction = true
          }else{
            renderer.hasTraction = false
          }
        }
        
        shapes.forEach(async shape => {
          switch(shape.name){
            case 'arrow 1':
            case 'arrow 2':
            break
            case 'bird ship':
              iplayers.map(async player => {
                if(+player.id != +playerData.id){
                  
                    
                  player.ix += (-player.x - player.ix) / lerpFactor
                  player.iy += (player.y - player.iy) / lerpFactor
                  player.iz += (player.z - player.iz) / lerpFactor
                  player.iroll += (player.roll - player.iroll) /
                                  lerpFactor
                  player.ipitch += (player.pitch - player.ipitch) /
                                   lerpFactor
                  player.iyaw += (player.yaw - player.iyaw) /
                                 lerpFactor
                  shape.x = player.ix
                  shape.y = player.iy
                  shape.z = -player.iz
                  shape.roll = player.iroll
                  shape.pitch = player.ipitch
                  shape.yaw = player.iyaw
                  
                  if(player.firingMissiles) fireMissile({
                    interpolated: true,
                    player,
                  })

                  if(player.firingChainguns) fireChainguns({
                    interpolated: true,
                    player,
                  })

                  if(typeof gunShape != 'undefined' && player.hasMissiles){
                    gunShape.x = shape.x
                    gunShape.y = shape.y
                    gunShape.z = shape.z
                    gunShape.roll = shape.roll
                    gunShape.pitch = shape.pitch
                    gunShape.yaw = shape.yaw
                    await renderer.Draw(gunShape)
                  }
                  await renderer.Draw(shape)

                  if(typeof chaingunShape != 'undefined' && player.hasChainguns){
                    chaingunShape.x = shape.x
                    chaingunShape.y = shape.y
                    chaingunShape.z = shape.z
                    chaingunShape.roll = shape.roll
                    chaingunShape.pitch = shape.pitch
                    chaingunShape.yaw = shape.yaw
                    await renderer.Draw(chaingunShape)
                  }
                  await renderer.Draw(shape)

                  drawPlayerNames({
                    x: shape.x,
                    y: shape.y,
                    z: shape.z,
                    roll: shape.roll,
                    pitch: shape.pitch,
                    yaw: shape.yaw,
                    name: player.name,
                  })
                  
                }
              })
            break
            case 'particles':
              for(var i=0; i<shape.vertices.length; i+=3){
                nax = nay = naz = 0
                ax = shape.vertices[i+0]
                ay = shape.vertices[i+1]
                az = shape.vertices[i+2]
                
                if(ax + renderer.x > cl/1*sp*mag) nax -= cl*sp*2*mag
                if(ax + renderer.x < -cl/1*sp*mag) nax += cl*sp*2*mag
                if(ay + renderer.y > br/1*sp*mag) nay -= br*sp*2*mag
                if(ay + renderer.y < -br/1*sp*mag) nay += br*sp*2*mag
                if(az + renderer.z > br/1*sp*mag) naz -= br*sp*2*mag
                if(az + renderer.z < -br/1*sp*mag) naz += br*sp*2*mag

                shape.vertices[i+0] += nax
                shape.vertices[i+1] += nay
                shape.vertices[i+2] += naz
              }
              await renderer.Draw(shape)
            break
            case 'point light':
              shape.x = renderer.x
              shape.z = renderer.z
              shape.y = renderer.y + 250 //- floor(shape.x, shape.z) + 450
              await renderer.Draw(shape)
            break
            case 'background':
              shape.x = -renderer.x
              shape.y = -renderer.y / 2 + 250
              shape.z = -renderer.z
              await renderer.Draw(shape)
            break
            case 'floor':
              for(var i=0; i<shape.vertices.length; i+=9){
                ax = ay = az = nax = nay = naz = 0

                for(var m = 3; m--;){
                  ax += shape.vertices[i+m*3+0]
                  //ay += shape.vertices[i+m*3+1]
                  az += shape.vertices[i+m*3+2]
                }
                ax /= 3
                //ay /= 3
                az /= 3
                
                if(ax + renderer.x > cl/1*sp*mag) nax -= cl*sp*2*mag
                if(ax + renderer.x < -cl/1*sp*mag) nax += cl*sp*2*mag
                if(az + renderer.z > br/1*sp*mag) naz -= br*sp*2*mag
                if(az + renderer.z < -br/1*sp*mag) naz += br*sp*2*mag
                
                for(var m = 3; m--;){
                  shape.vertices[i+m*3+0] += nax
                  shape.vertices[i+m*3+2] += naz
                  shape.vertices[i+m*3+1] = floor(shape.vertices[i+m*3+0],
                                              shape.vertices[i+m*3+2]) - 60
                }
              }
              //if(!((t*60|0)%240) || (t<.1)) Coordinates.SyncNormals(shape, true)
              await renderer.Draw(shape)
            break
            default:
            break
          }
        })

        if(typeof splosionShape != 'undefined'){
          splosions = splosions.filter(splosion => splosion.age > .1)
          splosions.map(async splosion => {
            for(var j = 0; j < splosionShape.vertices.length; j+=3){
              var l = splosion.data[j/3|0]
              var fl = floor(l[0] + l[3] + splosion.x, l[2] + l[5] + splosion.z) - 60
              if(l[1] + l[4] < fl) l[4] = Math.abs(l[4])
              splosionShape.vertices[j+0] = l[0] += l[3]
              splosionShape.vertices[j+1] = l[1] += l[4] -= grav
              splosionShape.vertices[j+2] = l[2] += l[5]
              splosionShape.vertices[j+1] = Math.max(fl+1, splosionShape.vertices[j+1])
            }
            splosionShape.size = 40 * splosion.age**.5
            splosion.age -= .001
            splosionShape.x = splosion.x
            splosionShape.y = splosion.y
            splosionShape.z = splosion.z
            splosionShape.roll = 0
            splosionShape.pitch = 0
            splosionShape.yaw = 0
            await renderer.Draw(splosionShape)
          })
        }
        
        if(typeof sparksShape != 'undefined'){
          sparks = sparks.filter(sparks => sparks.age > .1)
          sparks.map(async sparks => {
            for(var j = 0; j < sparksShape.vertices.length; j+=3){
              var l = sparks.data[j/3|0]
              var fl = floor(l[0] + l[3] + sparks.x, l[2] + l[5] + sparks.z) - 60
              if(l[1] + l[4] < fl) l[4] = Math.abs(l[4])
              sparksShape.vertices[j+0] = l[0] += l[3]
              sparksShape.vertices[j+1] = l[1] += l[4] -= grav
              sparksShape.vertices[j+2] = l[2] += l[5]
              sparksShape.vertices[j+1] = Math.max(fl+1, sparksShape.vertices[j+1])
            }
            sparksShape.size = 32 * sparks.age**2
            sparks.age -= .05
            sparksShape.x = sparks.x
            sparksShape.y = sparks.y
            sparksShape.z = sparks.z
            sparksShape.roll = 0 //sparks.roll
            sparksShape.pitch = 0 //sparks.pitch
            sparksShape.yaw = 0 //sparks.yaw
            await renderer.Draw(sparksShape)
          })
        }
        
        if(typeof missileShape != 'undefined'){
          missiles = missiles.filter(missile => renderer.t - missile.t < missileLife)
          missiles.map(async missile => {
            missileShape.x = missile.x += missile.vx
            missileShape.y = missile.y += missile.vy
            missileShape.z = missile.z += missile.vz
            if(missile.y < floor(missile.x, missile.z)){
              missile.t = -missileLife
              spawnSplosion(missile.x, missile.y, missile.z)
            } else {
              missileShape.roll = missile.roll
              missileShape.pitch = missile.pitch
              missileShape.yaw = missile.yaw
              await renderer.Draw(missileShape)
              
              var offset = Coordinates.R_pyr(0, -5, -22, missile)
              thrusterShape.x = missile.x + offset[0]
              thrusterShape.y = missile.y + offset[1]
              thrusterShape.z = missile.z + offset[2]
              thrusterShape.roll = missile.roll
              thrusterShape.pitch = missile.pitch
              thrusterShape.yaw = missile.yaw
              await renderer.Draw(thrusterShape)
            }
          })
        }

        //if(typeof bulletShape != 'undefined'){
        if(typeof bulletParticles != 'undefined'){
          bullets = bullets.filter(bullet => renderer.t - bullet.t < chaingunLife)
          var l = bulletParticles.vertices
          for(var i = 0; i < l.length; i++) l[i] = 1e6
          bullets.map((bullet, idx) => {
            l[idx*3+0] = bullet.x += bullet.vx
            l[idx*3+1] = bullet.y += bullet.vy
            l[idx*3+2] = bullet.z += bullet.vz
            if(bullet.y < floor(bullet.x, bullet.z)){
              bullet.t = -chaingunLife
              spawnSparks(bullet.x, bullet.y, bullet.z)
            } else {
              //bulletShape.roll = bullet.roll
              //bulletShape.pitch = bullet.pitch
              //bulletShape.yaw = bullet.yaw
            }
          })
          await renderer.Draw(bulletParticles)
        }


        flashes = flashes.filter(v => v.age > 0)
        flashes.map(async v => {
          muzzleFlair.x     = v.x
          muzzleFlair.y     = v.y
          muzzleFlair.z     = v.z
          if(typeof muzzleFlair != 'undefined'){
            var sz = 250 * v.age
            for(var j = 0; j < muzzleFlair.vertices.length; j++) {
              muzzleFlair.vertices[j]=muzzleFlairBase.vertices[j] * sz
            }
            await renderer.Draw(muzzleFlair)
          }
          v.age -= .25
        })

      }
      launch(960, 540)

      // db sync
      const URLbase = 'https://boss.mindhackers.org/flock'
      
      const syncPlayers = data => {
        players = data.map(player => {
          player = JSON.parse(player)
          player.id = +player.id
          return player
        })
        if(!players.filter(v=>+v.id==+playerData.id).length){
          reconnectionAttempts++
          if(reconnectionAttempts < 10){
            console.log('reconnecting...')
            coms('reconnect.php', 'syncPlayers')
          }else{
            console.log('connection unavailable... fail X 10')
          }
        }else{
          reconnectionAttempts = 0
          iplayers.map(v=>{ v.keep = false})
          players.map(player => {
            var l = iplayers.filter(v=> (+v.id == +player.id))
            if(l.length){
              //l[0].name  = player.name
              //l[0].id    = player.id
              var v = l[0]
              v.hasMissiles     = player.hasMissiles
              v.hasChainguns    = player.hasChainguns
              v.firingMissiles  = player.firingMissiles
              v.firingChainguns = player.firingChainguns
              v.name            = player.name
              v.x               = player.x
              v.y               = player.y
              v.z               = player.z
              v.roll            = player.roll
              v.pitch           = player.pitch
              v.yaw             = player.yaw
              v.keep            = true
            }else{
              var newObj = {
                name: '', id: -1,
                x: 0, y: 0, z: 0,
                roll: 0, pitch: 0, yaw: 0,
                ix: 0, iy: 0, iz: 0,
                iroll: 0, ipitch: 0, iyaw: 0,
                keep: true,
              }
              newObj.missileAlt        = false
              newObj.chaingunAlt       = false
              newObj.hasMissiles       = player.hasMissiles
              newObj.hasChainguns      = player.hasChainguns
              newObj.missileShotTimer  = player.missileShotTimer
              newObj.chaingunShotTimer = player.chaingunShotTimer
              newObj.firingMissiles    = player.firingMissiles
              newObj.name              = player.name
              newObj.id                = +player.id
              newObj.x                 = newObj.ix     = player.x
              newObj.y                 = newObj.iy     = player.y
              newObj.z                 = newObj.iz     = player.z
              newObj.roll              = newObj.iroll  = player.roll
              newObj.pitch             = newObj.ipitch = player.pitch
              newObj.yaw               = newObj.iyaw   = player.yaw
              iplayers.push(newObj)
            }
          })
          iplayers = iplayers.filter(v=>v.keep)
        }
      }
      
      window.updatePlayerName = e => {
        if(!playerName.value) return
        playerName.value = playerName.value.substr(0, 20)
        var params = location.href.split('?')
        if(params.length > 1){
          params = params[1].split('&').filter(v=>{
            return v.toLowerCase().indexOf('name=') == -1
          }).join('&')
          params = '?name=' + playerName.value + (params ? '&' : '') + params
        }else{
          params = '?name=' + playerName.value
        }
        var newURL = location.href.split('?')[0] + params
        playerData.name = playerName.value
        history.replaceState({}, document.title, newURL)
      }
      
      const launchLocalClient = data => {
        playerData = data
        playerData.id = +playerData.id
        
        var pn = location.href.split('name=')
        if(pn.length>1){
          pn = pn[1].split('&')[0]
          playerData.name = playerName.value = decodeURIComponent(pn)
        } else {
          playerName.value = playerData.name
          updatePlayerName()
        }
        
        
        setInterval(() => {
          coms('sync.php', 'syncPlayers')
        }, 1e3)
      }
    
      const coms = (target, callback='') => {
        let sendData = { playerData }
        var url = URLbase + '/' + target
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(sendData),
        }).then(res => res.json()).then(data => {
          if(callback) eval(callback + '(data)')
        })
      }
      
      const gameSync = () => {
        playerData.x = renderer.x
        playerData.y = -renderer.y
        playerData.z = renderer.z
        playerData.roll = -renderer.roll
        playerData.pitch = -renderer.pitch
        playerData.yaw = -renderer.yaw
      }

      window.selectMaybe = e => {
        if(document.activeElement.id != 'playerName') {
          setTimeout(()=>{e.select()},0)
        }
      }
      
      renderer.x     = (Rn() - .5) * 200
      renderer.z     = (Rn() - .5) * 200
      renderer.y     = -floor(renderer.x, renderer.z) - 20
      renderer.yaw   = (Rn() - .5) * Math.PI*2
      renderer.pitch = (Rn() - .5) * Math.PI/3

      // network payload (tranceived properties)
      var playerData = {
        name: '', id: -1,
        x: renderer.x,
        y: renderer.y,
        z: renderer.z,
        roll: 0, pitch: 0, yaw: 0,
        hasMissiles: true,
        hasChainguns: true,
        firingMissiles: false,
        interpolated: false,
      }
  
      coms('launch.php', 'launchLocalClient')
    </script>
  </body>
</html>


FILE;
file_put_contents('../../flock/index.html', $file);
?>