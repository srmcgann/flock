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
        src="https://bosstools.mooo.com/remapper/loading.mp4"
      ></video>
    </div>
    <script type="module">
    
      // net-game boilerplate
      var X, Y, Z, roll, pitch, yaw
      var reconnectionAttempts = 0
      var gameLoaded = false
      var lerpFactor = 20
      var players    = []
      var iplayers   = []  // ip local mirror

      ///////////////////////
      

      // game guts
      
      import * as Coordinates from
      "./coordinates.js"
      
      var S = Math.sin
      var C = Math.cos
      var Rn = Math.random
    
      const floor = (X, Z) => {
        //return Math.min(8, Math.max(-.25, (S(X/2e3+renderer.t/8) * S(Z/2e3) + S(X/2500) * S(Z/2500+renderer.t * 3 / 8)) ** 3)) * 2e3

        return Math.min(1, Math.max(-.5, (S(X/5e3) * S(Z/5e3) + S(X/1e4) * S(Z/1e4)) ** 3)) * 5e3

        //return Math.min(4, Math.max(-.5, (S(X/1e3+renderer.t/4) * S(Z/1e3) + S(X/2500) * S(Z/2500+renderer.t*3/4)) ** 3)) * 1e3
      }

      var X, Y, Z
      var cl = 10
      var rw = 1
      var br = 10
      var sp = 160
      var fcl = 4 * 25
      var frw = 1
      var fbr = 4 * 25
      var fsp = 20
      var tx, ty, tz
      var ls = 2**.5 / 2 * sp, p, a
      var fls = 2**.5 / 2 * fsp, p, a
      var texCoords = []
      var minX = 6e6, maxX = -6e6
      var minZ = 6e6, maxZ = -6e6
      var mag = 12.5 //20 * (2**.5/2)
      var ax, ay, az, nax, nay, naz
      var missileHoming = .15
      var missileDamage = .25
      var chaingunDamage = .025
      var gunShape, missileShape, bulletShape, tractorShape
      var muzzleFlair, chaingunShape, tractorShapeBaseVertices
      var muzzleFlairBase, thrusterShape, thrusterPowerupShape
      var medkitShape, skullShape
      var sparksShape, splosionShape, weaponsTrackShape
      var bulletParticles, floorParticles
      var smokeParticles
      var showMenu                 = false
      var mST                      = 0
      var mSTInterval              = .2
      var missileSpeed             = 300
      var missileLife              = 6
      var cST                      = 0
      var cSTInterval              = .01
      var chaingunSpeed            = 400
      var chaingunLife             = 8
      var smokeLife                = 6
      var missilePowerupShape, powerupAuras, powerupRespawnSpeed = 10
      var maxPlayerVel = 200
      

      var refTexture = './pseudoEquirectangular_3.jpg'
      var heightMap = 'https://srmcgann.github.io/Coordinates/resources/bumpmap_equirectangular_po2.jpg'
      var floorMap = './floorCircuitry.jpg'
      
      var dmOverlay = new Image()
      dmOverlay.src = './damage.png'

      var dmDeadOverlay = new Image()
      dmDeadOverlay.src = './damage_dead.png'

      var rendererOptions = {
        ambientLight: .2,
        width: 960,
        height: 540,
        margin: 0,
        fov: 1600
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      rendererOptions.attachToBody = false
      rendererOptions.fov = 1500 / 4
      rendererOptions.width = renderer.width / 2
      rendererOptions.height = renderer.height / 2
      var scratchCanvas = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 10
      
      Coordinates.AnimationLoop(renderer, 'Draw')

      var grav = 20
      var playervx = 0
      var playervy = 0
      var playervz = 0
      renderer.c.onmousedown = e => {
        if(document.activeElement.nodeName == 'CANVAS' && (!renderer.flyMode &&
           renderer.hasTraction) && e.button == 2){
          playervy -= 600
        }
      }

      var shapes       = []
      var missiles     = []
      var bullets      = []
      var smoke        = []
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
          {lighting: { type: 'ambientLight', value: .25}},
          { uniform: {
            type: 'phong',
            value: .2
          } },
          { uniform: {
            type: 'reflection',
            playbackSpeed: 2,
            enabled: true,
            map: refTexture,
            value: .5
          } },
        ]
        var powerupShader = await Coordinates.BasicShader(renderer, shaderOptions)

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
            map: refTexture,
            enabled: false,
            value: .2,
          } },
        ]
        var weaponsTrackShader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          {lighting: { type: 'ambientLight', value: -.05}},
          { uniform: {
            type: 'phong',
            value: 0
          } },
          { uniform: {
            type: 'reflection',
            enabled: false,
            map: refTexture,
            value: .5
          } },
        ]
        var floorShader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          { lighting: {type: 'ambientLight', value: .35},
          },
          { uniform: {
            type: 'phong',
            value: 0
          } }
        ]
        var backgroundShader = await Coordinates.BasicShader(renderer, shaderOptions)


        var shaderOptions = [
          { uniform: {
            type: 'phong',
            value: .15
          } },
          { uniform: {
            type: 'reflection',
            value: .4,
            map: refTexture
          } },
        ]
        var skullShader = await Coordinates.BasicShader(scratchCanvas, shaderOptions)
        var geoOptions = {
          shapeType: 'custom shape',
          url: 'https://srmcgann.github.io/Coordinates/custom shapes/skull.json',
          map: 'https://srmcgann.github.io/Coordinates/custom shapes/skull.jpg',
          colorMix: 0,
        }
        await Coordinates.LoadGeometry(scratchCanvas, geoOptions).then(async (geometry) => {
          skullShape = geometry
          await skullShader.ConnectGeometry(geometry)
        })  


        var geometryData = Array(5e3).fill().map(v=> [1e7, 1e7, 1e7])
        var geoOptions = {
          shapeType: 'particles',
          name: 'smoke particles',
          geometryData,
          size: 150,
          alpha: .4,
          penumbra: .3,
          color: 0xeecc88,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          smokeParticles = geometry
        })


        var geoOptions = {
          shapeType: 'custom shape',
          url: './birdship.json',
          map: './birdship.png',
          name: 'bird ship',
          scaleX: 10,
          scaleY: 10,
          scaleZ: 10,
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
          size: 5,
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
          size: 20,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            thrusterShape = geometry
          })
        }

        var geoOptions = {
          shapeType: 'sprite',
          map: 'medkit_lowres.png?2',
          name: 'medkit',
          size: 20,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            medkitShape = geometry
          })
        }

        var geoOptions = {
          shapeType: 'sprite',
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star1.png',
          name: 'thruster powerup shape',
          size: 50,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            thrusterPowerupShape = geometry
          })
        }

        var geoOptions = {
          shapeType: 'custom shape',
          map: 'https://bosstools.mooo.com/assets/uploads/1xPqgj.jpeg',
          url: './weaponsTrack.json',
          //url: 'https://srmcgann.github.io/objs/track.obj',
          //averageNormals: true,
          scaleX: 6.33,
          scaleZ: 6.33,
          x: 0,
          y: 0,
          z: 0,
          size: 1,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            weaponsTrackShape = geometry
            await weaponsTrackShader.ConnectGeometry(geometry)
          })
        }

        var geoOptions = {
          shapeType: 'sprite',
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star7.png',
          name: 'tractor',
          x: 0, z: 0,
          y: floor(0,0) + 2e4,
          involveCache: false,
          size: 100,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            tractorShape = geometry
            tractorShapeBaseVertices = structuredClone(geometry.vertices)
          })
        }

        powerupAuras = Array(6).fill()
        for(var m = 0; m<6; m++){
          var geoOptions = {
            shapeType: 'sprite',
            map: './powerup_' + (m + 1) + '.png',
            name: 'powerup aura ' + (m + 1),
            scaleY: .66,
            size: 64
          }
          if(1){
            await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
              powerupAuras[m] = {
                geometry,
                nextRespawn: 0
              }
            })
          }
        }
        
        var geoOptions = {
          shapeType: 'custom shape',
          url: './missilePowerup.json',
          map: './birdship.png',
          name: 'missilePowerup',
          scaleX: 6,
          scaleY: 4,
          scaleZ: 6,
          //exportShape: true,
        }
        if(1){
          await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
            missilePowerupShape = geometry
            await powerupShader.ConnectGeometry(geometry)
          })
        }
        
        var geoOptions = {
          shapeType: 'custom shape',
          url: './guns.json',
          map: './birdship.png',
          name: 'gun shape',
          scaleX: 10,
          scaleY: 10,
          scaleZ: 10,
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
          url: './chainguns.json',
          map: './birdship.png',
          name: 'chainguns',
          scaleX: 10,
          scaleY: 10,
          scaleZ: 10,
          size: 1,
          rotationMode: 1,
          colorMix: 0,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          chaingunShape = geometry
          await shader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'obj',
          url: 'https://srmcgann.github.io/objs/bird ship/missile.obj',
          map: './birdship.png',
          name: 'missile',
          rotationMode: 1,
          colorMix: 0,
          scaleX: 50,
          scaleY: 50,
          scaleZ: 50,
          size: 1,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          missileShape = geometry
          await projectileShader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'custom shape',
          url: './bullet.json',
          map: './birdship.png',
          name: 'bullet',
          rotationMode: 1,
          colorMix: 0,
          scaleX: 10,
          scaleY: 10,
          scaleZ: 10,
          size: 1,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          bulletShape = geometry
          await projectileShader.ConnectGeometry(geometry)
        })

        var geoOptions = {
          shapeType: 'dodecahedron',
          name: 'background',
          subs: 2,
          size: 3e5,
          sphereize: 1,
          colorMix: 0,
          playbackSpeed: 1,
          scaleUVX: 6,
          scaleUVY: 6,
          map: refTexture,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
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
          scaleUVX: 2,
          scaleUVY: 2,
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
          shapes.push(geometry)
          console.log(geometry)
          await floorShader.ConnectGeometry(geometry)
          //Coordinates.SyncNormals(geometry, true, true)
        })

        /*geometryData = Array(fcl*frw*fbr).fill().map((v, i) => {
          tx = ((i%fcl) - fcl/2 + .5) * sp * ls * 2
          tz = ((i/fcl/frw|0) - fbr/2 + .5) * sp * ls * 2
          ty = floor(tx, tz)
          return [tx, ty, tz]
        })
        */
        
        var geoOptions = {
          shapeType: 'custom shape',
          //shapeType: 'particles',
          url: './floorGrid.json?3',
          name: 'floor particles',
          isParticle: true,
          size: 75,
          //geometryData,
          color: 0x4400ff,
          alpha: .75,
          penumbra: .2,
          //exportShape: true
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          floorParticles = geometry
        })

        
        var geoOptions = {
          shapeType: 'point light',
          name: 'point light',
          showSource: false,
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star0.png',
          size: 25,
          lum: 1e4,
          color: 0xffffff,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })  

        var iPc = 1e3
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
          size: 200,
          alpha: .3,
          penumbra: .3,
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
          size: 50,
          alpha: .7,
          penumbra: .5,
          color: 0x44ffcc,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          bulletParticles = geometry
        })

        var iPc  = 2500
        var iPv  = 200
        var geometryData = Array(iPc).fill().map(v=>{
          var vel = Rn() * 0 * iPv + iPv * 1
          var p, q, d
          var vx = S(p=Math.PI*2*Rn()) *
                       S(q=Rn() < .5 ? Math.PI/2*Rn()**.5 : Math.PI - Math.PI/2*Rn()**.5)* vel
          var vy = C(q) * vel * 1.25
          var vz = C(p) * S(q) * vel
          baseSplosion = [...baseSplosion, [vx, vy, vz, vx, vy, vz]]
          return [vx, vy, vz]
        })
        var geoOptions = {
          shapeType: 'particles',
          name: 'splosion particles',
          geometryData,
          x: 0, y: 0, z: 0,
          size: 75,
          alpha: .75,
          penumbra: .5,
          color: 0xffaa22,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          splosionShape = geometry
        })
        
        var iPc  = 500
        var iPv  = 75
        var geometryData = Array(iPc).fill().map(v=>{
          var vel = Rn() * .2 * iPv + iPv * .8
          var p, q, d
          var vx = S(p=Math.PI*2*Rn()) *
                       S(q=Rn() < .5 ? Math.PI/2*Rn()**.5 : Math.PI - Math.PI/2*Rn()**.5)* vel
          var vy = C(q) * vel * 1.25
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
          mSpeed: 500,
          flyMode: true,
          //crosshairMap: 'https://bosstools.mooo.com/assets/uploads/1rvQ0b.webp',
          crosshairSel: 2,
          crosshairSize: .5
        })
        
        window.onkeydown = e => {
          if(document.activeElement.nodeName == 'CANVAS'){
            if(!playerData.al) respawn()
            if(e.keyCode == 77){
              showMenu = !showMenu
            }
            if(e.keyCode == 70 && playerData.al){
              renderer.flyMode = !renderer.flyMode
            }
          }
        }

        document.querySelectorAll('.overlay').forEach(e => e.style.display = 'none')
        loadingVideo.pause()
        gameLoaded = true
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
        ctx.strokeStyle = shape.al ? '#6fc' : '#f20'
        ctx.fillStyle = shape.al ? '#6fc2' : '#f202'
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
        var fs = rad / 3
        ctx.font = fs+'px verdana'
        lx = pt[0]+lx/d*rad*3.25
        ly = pt[1]+ly/d*rad*2.2
        ctx.lineWidth = 6
        ctx.globalAlpha = 1
        ctx.fillStyle = shape.al ? '6fc' : '#f20'
        ctx.strokeStyle = '#000d'
        ctx.strokeText(shape.name, lx, ly-fs/3)
        ctx.fillText(shape.name, lx, ly-fs/3)

        ctx.strokeText('health ' + (shape.hl * 100 | 0), lx, ly-fs/3 + fs)
        ctx.fillText('health ' + (shape.hl * 100 | 0), lx, ly-fs/3 + fs)
      }
      
      const spawnSplosion = (x, y, z, vx, vy, vz) => {
        spawnSparks(x, y, z)
        var fl = floor(x, z)
        if(Math.abs(y - fl < 20)) y = fl - 55
        spawnFlash(x, y, z, 3)
        vx = (vx/3) //** 3 / 5
        vy = (vy/3) //** 3 / 5
        vz = (vz/3) //** 3 / 5
        var data = structuredClone(baseSplosion).map(v=>{
          v[3] += vx
          v[4] += vy
          v[5] += vz
          return v
        })
        splosions = [...splosions, {x, y, z, data, age: 1}]
      }
      
      const spawnSparks = (x, y, z) => {
        var fl = floor(x, z)
        if(Math.abs(y - fl < 20)) y = fl - 55
        sparks = [...sparks, {x, y, z, data: structuredClone(baseSparks), age: 1}]
      }
      
      const spawnFlash = (x, y, z, age = 1) => {
        flashes = [...flashes, { x, y, z, age }]
      }
      
      const fireMissile = player => {
        var actualPlayer = players.filter(v=>+v.id==+player.id)[0]
        if(!player.al || 
           renderer.t - actualPlayer.mST < mSTInterval) return
        var cont = true
        if(+player.id == +playerData.id){
          if(playerData.mCt > 0){
            playerData.mCt--
          }else{
            cont = false
          }
        }
        if(!cont) return
        var x, y, z, roll, pitch, yaw
        if(player.ip){
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
        
        actualPlayer.mST = renderer.t
        var p1 = yaw + Math.PI
        var p2 = -pitch + Math.PI / 2
        var vx = -S(p1) * S(p2) * missileSpeed
        var vy = C(p2) * missileSpeed
        var vz = -C(p1) * S(p2) * missileSpeed
        player.mA = !player.mA
        
        var offset = Coordinates.R_pyr(350 * (player.mA ? -1: 1), -100, 0, player)
        if(+player.id != +playerData.id) spawnFlash(-x + offset[0],
                                                     y + offset[1],
                                                     -z + offset[2], .5)
        
        offset = Coordinates.R_pyr(350 * (player.mA ? -1: 1), 0, 0, player)
        
        
        missiles = [...missiles, {
          x: -x + offset[0],
          y: y + offset[1],
          z: -z + offset[2],
          roll, pitch, yaw,
          t: renderer.t,
          id: player.id,
          vx, vy, vz,
        }]
        console.log('shooting missiles', actualPlayer)
        if(+player.id == +playerData.id) coms('sync.php', 'syncPlayers')
      }

      const fireChainguns = player => {
        var x, y, z, roll, pitch, yaw
        if(player.ip){
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
        
        var actualPlayer = players.filter(v=>+v.id==+player.id)[0]
        if(!player.al || 
           renderer.t - actualPlayer.cST < cSTInterval) return
        actualPlayer.cST = renderer.t
        var p1 = yaw + Math.PI
        var p2 = -pitch + Math.PI / 2
        var vx = -S(p1) * S(p2) * chaingunSpeed
        var vy = C(p2) * chaingunSpeed
        var vz = -C(p1) * S(p2) * chaingunSpeed
        player.cA = !player.cA
        
        var offset = Coordinates.R_pyr(200 * (player.cA ? -1: 1), -100, 0, player)
        if(+player.id != +playerData.id) spawnFlash(-x + offset[0],
                                                     y + offset[1],
                                                    -z + offset[2], .25)

        offset = Coordinates.R_pyr(200 * (player.cA ? -1: 1), 0, 0, player)
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
      
      var iSmokev = 6
      const genSmoke = (x, y, z) => {
        var vx = (Rn()-.5) * iSmokev
        var vy = (Rn()-.5) * iSmokev
        var vz = (Rn()-.5) * iSmokev
        smoke = [...smoke, {
          x: x,
          y: y,
          z: z,
          t: renderer.t,
          vx, vy, vz,
        }]
      }
      
      
      var weaponIconAnimations       = []
      var weaponIconAnimationsLoaded = false
      if(!weaponIconAnimations.length){
        var l
        ;(l=[
          './missile.mp4',
          './bullet.mp4',
        ]).map(async (url, idx) => {
          var obj = {
            resource: document.createElement('video'),
            loaded: false,
          }
          obj.resource.muted = true
          obj.resource.loop = true
          obj.resource.oncanplay = () => {
            obj.resource.play()
            obj.loaded = true
            if(weaponIconAnimations.filter(v=>v.loaded).length == l.length) weaponIconAnimationsLoaded = true
          }
          obj.resource.src = url
          weaponIconAnimations.push(obj)
        })
      }
      
      const drawMenu = () => {
        if(!gameLoaded) return
        var c = Coordinates.Overlay.c

        if(showMenu){
          
          ctx.beginPath()
          ctx.lineTo(c.width, c.height/2)
          ctx.lineTo(c.width / 2+100, c.height/2)
          ctx.lineTo(c.width / 2, c.height/2 + 100)
          ctx.lineTo(c.width / 2, c.height-0)
          ctx.lineTo(c.width, c.height-0)
          
          ctx.lineWidth = 10
          ctx.strokeStyle = '#40f2'
          ctx.stroke()
          ctx.lineWidth /= 6
          ctx.strokeStyle = '#40f'
          ctx.stroke()
          ctx.fillStyle = '#000d'
          ctx.fill()

          var fs = 16
          ctx.textAlign = 'left'
          ctx.font = (fs) + 'px verdana'
          ctx.fillStyle = '#fff'
          ctx.fillText(playerData.mCt, c.width/2 + 10, c.height - fs - c.height/16)
          ctx.fillText('[m] -> menu', c.width/2 + 10, c.height - fs)

          if(weaponIconAnimationsLoaded){
            var s = .25
            var res = weaponIconAnimations[playerData.gS].resource
            var w = res.videoWidth * s
            var h = res.videoHeight * s
            ctx.drawImage(res, c.width * .75 - w/2, c.height * .75 - h/2, w, h)
          }
        }else{
          ctx.beginPath()
          ctx.lineTo(c.width, c.height/2)
          ctx.lineTo(c.width / 1.05 +100, c.height/2)
          ctx.lineTo(c.width / 1.05, c.height/2 + 100)
          ctx.lineTo(c.width / 1.05, c.height-2)
          ctx.lineTo(c.width, c.height-2)
          
          ctx.lineWidth = 10
          ctx.strokeStyle = '#40f2'
          ctx.stroke()
          ctx.lineWidth /= 6
          ctx.strokeStyle = '#40f'
          ctx.stroke()
          ctx.fillStyle = '#102d'
          ctx.fill()
          
          var fs = 16
          ctx.textAlign = 'left'
          ctx.font = (fs) + 'px verdana'
          ctx.fillStyle = '#fff'
          ctx.fillText(playerData.mCt, c.width/1.05 + 10, c.height - fs - c.height / 16)
          ctx.fillText('[m]', c.width/1.05 + 10, c.height - fs)

          if(weaponIconAnimationsLoaded){
            var s = .1
            var res = weaponIconAnimations[playerData.gS].resource
            var w = res.videoWidth * s
            var h = res.videoHeight * s
            ctx.drawImage(res, c.width * .975 - w/2, c.height * .75 - h/2, w, h)
          }
        }
        
        if(playerData.al){
          if(playerData.dm > .02){
            console.log(playerData.dm)
            ctx.globalAlpha = playerData.dm
            ctx.drawImage(dmOverlay, 0, 0, c.width, c.height)
            ctx.globalAlpha = 1
          }else{
            playerData.dm = 0
          }
          playerData.dm = Math.max(0, playerData.dm /= 1.02)
        }else{
          ctx.globalAlpha = playerData.dm = 1
          scratchCanvas.Draw(skullShape)
          ctx.drawImage(scratchCanvas.c, 0,0, c.width, c.height)
          ctx.drawImage(dmDeadOverlay, 0, 0, c.width, c.height)
          scratchCanvas.z = 32
          skullShape.yaw += .02
        }
      }

      window.Draw = async () => {
        if(!gameLoaded) return
        var t = renderer.t
        gameSync()
        
        playerData.fM = false
        playerData.fC = false
        
        playerData.gS = playerData.mCt > 0 ? 0 : 1
        
        if(document.activeElement.nodeName == 'CANVAS'){
          if(renderer.mouseButton == -1){
            playerData.fM = false
            playerData.fC = false
          }
          if(!renderer.flyMode && renderer.mouseButton == 1) {
            switch(playerData.gS){
              case 0:
                fireMissile(playerData)
                playerData.fM = playerData.mCt > 0
              break
              case 1:
               fireChainguns(playerData)
               playerData.fC = true
              break
            }
          }
          renderer.keys.map((v, i) =>{
            if(v) {
              switch(i){
                case 90:
                  playerData.fM = playerData.mCt > 0
                  fireMissile(playerData)
                break
                case 88:
                  playerData.fC = true
                  fireChainguns(playerData)
                break
              }
            }
          })
        }
        
        var fl = -floor(-renderer.x, -renderer.z) - 1e3
        if(renderer.flyMode){
          if(renderer.y >= fl){
            renderer.y = fl
            playervy = 0
          }
        }else{
          /*
          var pox = renderer.x
          var poy = renderer.y
          var poz = renderer.z
          renderer.x += playervx
          renderer.y += playervy
          renderer.z += playervz
          playervx += (renderer.x - pox) / 16
          playervy += (renderer.y - poy) / 16
          playervz += (renderer.z - poz) / 16
          var d1 = Math.hypot(playervx, playervy, playervz) + .001
          var d2 = Math.min(d1, maxPlayerVel)
          playervx /= d1
          playervy /= d1
          playervz /= d1
          playervx *= d2
          playervy *= d2
          playervz *= d2
          */
          
          playervy += grav
          renderer.y += playervy
          if(renderer.y > fl - 20){
            renderer.y = fl
            playervy = 0
            renderer.hasTraction = true
          }else{
            renderer.hasTraction = false
          }
        }
          
        
        if(typeof smokeParticles != 'undefined'){
          smoke = smoke.filter(smoke => renderer.t - smoke.t < smokeLife)
          var l = smokeParticles.vertices
          for(var i = 0; i < l.length; i++) l[i] = 1e6
          smoke.map((smoke, idx) => {
            l[idx*3+0] = smoke.x += smoke.vx
            l[idx*3+1] = smoke.y += smoke.vy
            l[idx*3+2] = smoke.z += smoke.vz
            //if(smoke.y < floor(smoke.x, smoke.z)) smoke.t = -smokeLife
          })
          await renderer.Draw(smokeParticles)
        }
        
        var mcl = 3
        var mrw = 1
        var mbr = 3
        var msp = 1e5
        for(var i = 0; i<mcl*mrw*mbr; i++){
          medkitShape.x = ((i%mcl)-mcl/2 + .5) * msp
          medkitShape.z = ((i/mcl/mrw|0)-mbr/2 + .5) * msp
          medkitShape.y = 500 + floor(medkitShape.x, medkitShape.z) + (((i/mcl|0)%mrw)-mrw/2 + .5) * msp
          if(Math.hypot(-playerData.x - medkitShape.x, 
                         playerData.y - medkitShape.y, 
                        -playerData.z - medkitShape.z) < 1e4){
              await renderer.Draw(medkitShape)
          }
        }
        
        
        var powerup = missilePowerupShape
        powerup.yaw -= .1
        
        if(typeof powerupAuras != 'undefined' && powerupAuras.length == 6){
          var p
          for(var o = 0; o<6;o++){
            if(powerupAuras[o].nextRespawn <= t){
              var sd = o + 1
              var px = S(p=Math.PI*2/6*o + t/6) * 15500 * 4
              var pz = C(p) * 15500 * 4
              var py = floor(px, pz) + 600
              var d = Math.hypot(-playerData.x - px, playerData.y - py, -playerData.z - pz)
              if(d < 2e4){
                if(d < 2e3){
                  powerupAuras[o].nextRespawn = t + powerupRespawnSpeed
                  playerData.mCt += o+1
                }else{
                  for(var i = sd == 1 ? 2: sd; i--;){
                    if(sd == 1 && i) continue
                    powerup.x = 
                      px + S(p=Math.PI*2/(sd==1?2:sd)*i + t*16 / (1+sd/2)) * 2e3
                    powerup.y = py
                    powerup.z = pz + C(p) * 2e3
                    await renderer.Draw(powerup)
                    
                    thrusterPowerupShape.x = powerup.x
                    thrusterPowerupShape.y = powerup.y -450
                    thrusterPowerupShape.z = powerup.z
                    await renderer.Draw(thrusterPowerupShape)
                  }
                }
              }
              powerupAuras[o].geometry.x = px
              powerupAuras[o].geometry.y = py
              powerupAuras[o].geometry.z = pz
              await renderer.Draw(powerupAuras[o].geometry)
            }
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
                    
                  drawPlayerNames({
                    x: shape.x,
                    y: shape.y,
                    z: shape.z,
                    roll: shape.roll,
                    pitch: shape.pitch,
                    yaw: shape.yaw,
                    name: player.name,
                    hl: player.hl,
                  })

                  shape.x = player.ix
                  shape.y = player.iy
                  shape.z = -player.iz
                  shape.roll = player.iroll
                  shape.pitch = player.ipitch
                  shape.yaw = player.iyaw
                  
                  if(player.fM) fireMissile({
                    id: player.id,
                    ip: true,
                    player,
                  })

                  if(player.fC) fireChainguns({
                    id: player.id,
                    ip: true,
                    player,
                  })

                  if(typeof gunShape != 'undefined' && player.hM){
                    gunShape.x = shape.x
                    gunShape.y = shape.y
                    gunShape.z = shape.z
                    gunShape.roll = shape.roll
                    gunShape.pitch = shape.pitch
                    gunShape.yaw = shape.yaw
                    await renderer.Draw(gunShape)
                  }
                  await renderer.Draw(shape)

                  if(typeof chaingunShape != 'undefined' && player.hC){
                    chaingunShape.x = shape.x
                    chaingunShape.y = shape.y
                    chaingunShape.z = shape.z
                    chaingunShape.roll = shape.roll
                    chaingunShape.pitch = shape.pitch
                    chaingunShape.yaw = shape.yaw
                    await renderer.Draw(chaingunShape)
                  }
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
              //shape.x = renderer.x
              //shape.z = renderer.z
              shape.y = floor(shape.x, shape.z) + 500 //- floor(shape.x, shape.z) + 450
              await renderer.Draw(shape)
            break
            case 'background':
              shape.x = -renderer.x
              shape.y = -renderer.y
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
                                              shape.vertices[i+m*3+2]) - 4e3
                }
              }
              //if(!((t*60|0)%240) || (t<.1)) Coordinates.SyncNormals(shape, true)
              await renderer.Draw(shape)
            break
            default:
            break
          }
        })
        
        for(var i = 0; i< weaponsTrackShape.vertices.length; i+=3){
          weaponsTrackShape.vertices[i+1] = floor(weaponsTrackShape.vertices[i+0],
                                           weaponsTrackShape.vertices[i+2]) - 1e3
        }
        renderer.Draw(weaponsTrackShape)
        
        for(var i = 0; i< tractorShape.vertices.length; i+=3){
          var s =  2+S(t*64)*.1
          tractorShape.vertices[i+0] = tractorShapeBaseVertices[i+0] * s
          tractorShape.vertices[i+1] = tractorShapeBaseVertices[i+1] * s
          tractorShape.vertices[i+2] = tractorShapeBaseVertices[i+2] * s
        }
        renderer.Draw(tractorShape)

        if(typeof splosionShape != 'undefined'){
          splosions = splosions.filter(splosion => splosion.age > 0)
          splosions.map(async splosion => {
            for(var j = 0; j < splosionShape.vertices.length; j+=3){
              var l = splosion.data[j/3|0]
              var fl = floor(l[0] + splosion.x + l[3], l[2] + splosion.z + l[5])
              if(l[1] + splosion.y + l[4]< fl - 55) {
                l[1] =  fl - 55 - splosion.y
                l[3] /= 1.25
                l[4] = Math.abs(l[4]) / 2.5
                l[5] /= 1.25
              }
              splosionShape.vertices[j+0] = l[0] += l[3]
              splosionShape.vertices[j+1] = (l[1] += l[4] -= grav / 10)
              splosionShape.vertices[j+2] = l[2] += l[5]
            }
            splosionShape.size = 300 * splosion.age**2
            splosion.age -= .003
            splosionShape.x = splosion.x
            splosionShape.y = splosion.y
            splosionShape.z = splosion.z
            await renderer.Draw(splosionShape)
          })
        }
        
        if(typeof sparksShape != 'undefined'){
          sparks = sparks.filter(sparks => sparks.age > 0)
          sparks.map(async sparks => {
            for(var j = 0; j < sparksShape.vertices.length; j+=3){
              var l = sparks.data[j/3|0]
              var fl = floor(l[0] + sparks.x + l[3], l[2] + sparks.z + l[5])
              if(l[1] + sparks.y + l[4]< fl - 55) {
                l[1] =  fl - 55 - sparks.y
                l[3] /= 1.25
                l[4] = Math.abs(l[4]) / 2.5
                l[5] /= 1.25
              }
              sparksShape.vertices[j+0] = l[0] += l[3]
              sparksShape.vertices[j+1] = (l[1] += l[4] -= grav / 10)
              sparksShape.vertices[j+2] = l[2] += l[5]
            }
            sparksShape.size = 150 * sparks.age**2
            sparks.age -= .01
            sparksShape.x = sparks.x
            sparksShape.y = sparks.y
            sparksShape.z = sparks.z
            await renderer.Draw(sparksShape)
          })
        }
        
        if(typeof missileShape != 'undefined'){
          missiles = missiles.filter(missile => {
            var ret = renderer.t - missile.t < missileLife
            if(!ret) spawnSplosion(missile.x, missile.y, missile.z,
                                   missile.vx, missile.vy, missile.vz)
            return ret
          })
          missiles.map(async missile => {

            // heat-seeking
            var mx = missile.x
            var my = missile.y
            var mz = missile.z
            if(Rn() < 1) genSmoke(mx, my, mz)
              
            var mind = 6e6
            var d, midx = -1
            players.map((player, idx) => {
              if(+player.id != +missile.id &&
                 (d=Math.hypot(mx + player.x, my - player.y, mz + player.z)) < mind){
                  mind = d
                  midx = idx
              }
            })
            if(midx != -1){
              if(mind > missileSpeed * 1.25) {
                var tx = -players[midx].x
                var ty = players[midx].y
                var tz = -players[midx].z
                
                var p1a = missile.yaw
                var p1b = Math.atan2(tx-mx, tz-mz)
                var p2a = missile.pitch
                var p2b = Math.PI /2 - Math.acos((ty-my) / Math.hypot(tx-mx,ty-my,tz-mz))
                
                while(Math.abs(p1a - p1b) > Math.PI){
                  if(p1a > p1b){
                    p1b += Math.PI * 2
                  }else{
                    p1a += Math.PI * 2
                  }
                }
                
                missile.yaw -= Math.min(missileHoming/2, Math.max(-missileHoming/2, p1a-p1b))
                missile.pitch -= Math.min(missileHoming/2, Math.max(-missileHoming/2, p2a-p2b))
                
                var p1 = missile.yaw + Math.PI
                var p2 = -missile.pitch + Math.PI / 2
                missile.vx = -S(p1) * S(p2) * missileSpeed
                missile.vy = C(p2) * missileSpeed
                missile.vz = -C(p1) * S(p2) * missileSpeed
              }else{
                console.log('missile hit!')
                missile.t = -missileLife
                spawnSplosion(missile.x, missile.y, missile.z,
                              missile.vx, missile.vy, missile.vz)
                if(+players[midx].id == +playerData.id){
                  playerData.hl -= missileDamage
                  if(playerData.hl <= 0){
                    playerData.hl = 0
                    playerData.dm = 1
                    playerData.al = false
                    renderer.useKeys = false
                    spawnSplosion(playerData.x, playerData.y, playerData.z, 0, 0, 0)
                  }else{
                    playerData.dm += missileDamage * 4
                  }
                }
              }
            }
            
            if(missile.y + missile.vy < floor(missile.x + missile.vx, missile.z + missile.vz)){
              missile.t = -missileLife
              spawnSplosion(missile.x, missile.y, missile.z,
                            missile.vx, missile.vy, missile.vz)
            } else {
              missileShape.x = missile.x += missile.vx
              missileShape.y = missile.y += missile.vy
              missileShape.z = missile.z += missile.vz
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

        if(typeof bulletParticles != 'undefined'){
          bullets = bullets.filter(bullet => renderer.t - bullet.t < chaingunLife)
          var l = bulletParticles.vertices
          for(var i = 0; i < l.length; i++) l[i] = 1e6
          bullets.map((bullet, idx) => {
            var mx = bullet.x
            var my = bullet.y
            var mz = bullet.z
            var d
            players.map((player, idx) => {
              if(+player.id != +bullet.id){
                d=Math.hypot(mx + player.x, my - player.y, mz + player.z)
                if(d < chaingunSpeed * 1.25){
                  console.log('chaingun hit!')
                  bullet.t = -chaingunLife
                  if(Rn() < .5) spawnSparks(bullet.x, bullet.y, bullet.z)
                  if(+player.id == +playerData.id){
                    playerData.hl -= chaingunDamage
                    if(playerData.hl <= 0){
                      playerData.hl = 0
                      playerData.dm = 1
                      playerData.al = false
                      renderer.useKeys = false
                      spawnSplosion(playerData.x, playerData.y, playerData.z, 0, 0, 0)
                    }else{
                      playerData.dm += chaingunDamage * 8
                    }
                  }
                }
              }
            })
            
            l[idx*3+0] = bullet.x += bullet.vx
            l[idx*3+1] = bullet.y += bullet.vy
            l[idx*3+2] = bullet.z += bullet.vz
            if(bullet.y < floor(bullet.x, bullet.z)){
              bullet.t = -chaingunLife
              if(Rn() < .33) spawnSparks(bullet.x, bullet.y, bullet.z)
            }
          })
          await renderer.Draw(bulletParticles)
        }

        for(var i=0; i<floorParticles.vertices.length; i+=3){
          ax = ay = az = nax = nay = naz = 0
          ax = floorParticles.vertices[i+0]
          az = floorParticles.vertices[i+2]
          
          if(ax + renderer.x > fcl*8*fls) nax -= fcl*8*fls*2
          if(ax + renderer.x < -fcl*8*fls) nax += fcl*8*fls*2
          if(az + renderer.z > fbr*8*fls) naz -= fbr*8*fls*2
          if(az + renderer.z < -fbr*8*fls) naz += fbr*8*fls*2
          
          floorParticles.vertices[i+0] += nax
          floorParticles.vertices[i+2] += naz
          floorParticles.vertices[i+1] = floor(floorParticles.vertices[i+0],
                                      floorParticles.vertices[i+2]) - 300
        }
        await renderer.Draw(floorParticles)

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
        
        drawMenu()

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
          }
        })
      }
      launch(renderer.width, renderer.height)

      // db sync
      const URLbase = 'https://bosstools.mooo.com/flock'
      
      const syncPlayers = data => {
        var tPlayers = structuredClone(players)
        players = data.map(player => {
          player = JSON.parse(player)
          player.id = +player.id
          return player
        })
        tPlayers.map(v=>{
          var tp = players.filter(q=>+q.id == +v.id)
          if(tp.length) {
            tp[0].mST = v.mST
            tp[0].cST = v.cST
          }
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
              v.al              = player.al
              v.dm              = player.dm
              v.hM              = player.hM
              v.hC              = player.hC
              v.mST             = player.mST
              v.cST             = player.cST
              v.fM              = player.fM
              v.fC              = player.fC
              v.hl              = player.hl
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
              newObj.mA                = false
              newObj.cA                = false
              newObj.dm                = player.dm
              newObj.al                = player.al
              newObj.hM                = player.hM
              newObj.hC                = player.hC
              newObj.mST               = player.mST
              newObj.cST               = player.cST
              newObj.fM                = player.fM
              newObj.hl                = player.hl
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
        }, 640)
      }
    
      const coms = (target, callback='') => {
        playerData.x = Math.round(playerData.x*1e3) / 1e3
        playerData.y = Math.round(playerData.y*1e3) / 1e3
        playerData.z = Math.round(playerData.z*1e3) / 1e3
        playerData.roll = Math.round(playerData.roll*1e3) / 1e3
        playerData.pitch = Math.round(playerData.pitch*1e3) / 1e3
        playerData.yaw = Math.round(playerData.yaw*1e3) / 1e3
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

      const respawn = () => {
        renderer.useKeys = true
        ls = Rn()**.5*1e5
        var x = S(p=Math.PI*Rn()*2) * ls
        var z = C(p) * ls
        playerData = {
          name: '', id: -1,
          hl: 1, al: true,
          y: floor(x, z) + 500,
          gS: 0, mCt: 0,
          roll: 0, pitch: 0, yaw: 0,
          mST: 0, cST: 0,
          hM: true,
          hC: true,
          fM: false,
          fC: false,
          ip: false,
          dm: 0,
        }
      }

      var playerData
      respawn()

      coms('launch.php', 'launchLocalClient')
    </script>
  </body>
</html>
