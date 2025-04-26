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
    </style>
  </head>
  <body>
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
      var lerpFactor = 20
      var players    = []
      var iplayers   = []  // interpolated local mirror
      ///////////////////////
      

      // game guts
      
      import * as Coordinates from
      "https://boss.mindhackers.org/Coordinates/coordinates.js"
      
      var S = Math.sin
      var C = Math.cos
      var Rn = Math.random
    
      const floor = (X, Z) => {
        //var d = Math.hypot(X, Z) / 500
        return (S(X/100) * S(Z/100)) * 40
      }
      var X, Y, Z
      var cl = 16
      var rw = 1
      var br = 16
      var sp = 2
      var tx, ty, tz
      var ls = 2**.5 / 2 * sp, p, a
      var texCoords = []
      var minX = 6e6, maxX = -6e6
      var minZ = 6e6, maxZ = -6e6
      var mag = 12.5 //20 * (2**.5/2)
      var ax, ay, az, nax, nay, naz


      var refTexture = 'https://i.imgur.com/CISa4Gt.jpg'
      var heightMap = 'https://srmcgann.github.io/Coordinates/resources/earth_heightmap_lowres.jpg'
    
      var rendererOptions = {
        ambientLight: .5,
        width: 960,
        height: 540,
        margin: 0,
        fov: 600
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 10
      
      Coordinates.AnimationLoop(renderer, 'Draw')

      var grav = .666 / 4
      var playervy = 0
      renderer.c.onmousedown = e => {
        if(!renderer.flyMode && renderer.hasTraction && e.button == 2){
          playervy -= 10
        }
      }


      var shapes = []
      

      var launch = async (width, height) => {
        var ar = width / height
        width = Math.min(1e3, width)
        height = width / ar
        await Coordinates.ResizeRenderer(renderer, width, height)
        renderer.fov = Math.hypot(width, height) / 2
        //renderer.optionalPlugins[0].enabled = plugin

        var shaderOptions = [
          { uniform: {
            type: 'phong',
            value: .5
          } },
          { uniform: {
            type: 'reflection',
            enabled: false,
            map: refTexture,
            value: .5
          } },
        ]
        var shader = await Coordinates.BasicShader(renderer, shaderOptions)

        var shaderOptions = [
          { lighting: {type: 'ambientLight', value: -.15},
          },
          { uniform: {
            type: 'phong',
            value: 0
          } }
        ]
        var backgroundShader = await Coordinates.BasicShader(renderer, shaderOptions)


        var geoOptions = {
          shapeType: 'sprite',
          name: 'player graphic',
          color: 0xffffff,
          map: 'https://srmcgann.github.io/Coordinates/resources/stars/star0.png',
          size: 20,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })  
        
        var geoOptions = {
          shapeType: 'cube',
          name: 'player graphic',
          color: 0xffffff,
          size: 5,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
          await shader.ConnectGeometry(geometry)
        })
        


        var geoOptions = {
          shapeType: 'point light',
          name: 'point light',
          showSource: true,
          size: 20,
          lum: 120,
          color: 0xffffff,
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })

        var geoOptions = {
          shapeType: 'cylinder',
          name: 'background',
          subs: 0,
          scaleUVX: 1,
          scaleUVY: 1,
          //scaleY: .75,
          pitch: 0,
          size: 450,
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
          averageNormals: true, 
          geometryData,
          scaleUVX: 2,
          scaleUVY: 2,
          texCoords,
          color: 0xffffff,
          colorMix: 0,
          fipNormals: true,
          //pitch: Math.PI,
          //map: heightMap,
          map: 'https://srmcgann.github.io/Coordinates/resources/nebugrid_po2.jpg',
          //heightMap,
          //heightMapIntensity: 80,
          playbackSpeed: 1
        }
        if(0) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          Coordinates.SyncNormals(geometry, true, true)
          shapes.push(geometry)
          await shader.ConnectGeometry(geometry)
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
          size: 8,
          alpha: .25,
          penumbra: .5,
          color: 0x88ffcc,
        }
        if(1) await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          shapes.push(geometry)
        })  
        
        Coordinates.LoadFPSControls(renderer, {
          flyMode: false,
          mSpeed: 5,
          flyMode: true,
          crosshairMap: 'https://boss.mindhackers.org/assets/uploads/1rvQ0b.webp',
          crosshairSel: 3,
          crosshairSize: .25
        })
        
        window.onkeydown = e => {
          if(e.keyCode == 70){
            renderer.flyMode = !renderer.flyMode
          }
        }
        
        document.querySelectorAll('.overlay').forEach(e => e.style.display = 'none')
        loadingVideo.pause()
      }
      
      window.Draw = () => {
      
        Coordinates.overlay.ctx.fillStyle = '#f00'
        Coordinates.overlay.ctx.fillRect(100,100,100,100)
      
        gameSync()
        var t = renderer.t
        if(!renderer.flyMode){
          playervy += grav
          renderer.y += playervy
          var fl = -floor(-renderer.x, -renderer.z) - 50
          if(renderer.y > fl - 3){
            renderer.y = fl
            playervy = 0
            renderer.hasTraction = true
          }else{
            renderer.hasTraction = false
          }
        }

        shapes.forEach(shape => {
          switch(shape.name){
            case 'player graphic':
              iplayers.map(player => {
                if(+player.id != +playerData.id){
                  player.ix += (player.x - player.ix) / lerpFactor
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
                  renderer.Draw(shape)
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
              renderer.Draw(shape)
            break
            case 'point light':
              shape.y = renderer.y - floor(shape.x, shape.z) + 450
              renderer.Draw(shape)
            break
            case 'background':
              shape.x = -renderer.x
              shape.y = -renderer.y / 2 + 250
              shape.z = -renderer.z
              renderer.Draw(shape)
            break
            case 'floor':
              for(var i=0; i<shape.vertices.length; i+=9){
                ax = ay = az = nax = nay = naz = 0

                for(var m = 3; m--;){
                  ax += shape.vertices[i+m*3+0]
                  ay += shape.vertices[i+m*3+1]
                  az += shape.vertices[i+m*3+2]
                }
                ax /= 3
                ay /= 3
                az /= 3
                
                if(ax + renderer.x > cl/1*sp*mag) nax -= cl*sp*2*mag
                if(ax + renderer.x < -cl/1*sp*mag) nax += cl*sp*2*mag
                if(az + renderer.z > br/1*sp*mag) naz -= br*sp*2*mag
                if(az + renderer.z < -br/1*sp*mag) naz += br*sp*2*mag
                
                for(var m = 3; m--;){
                  shape.vertices[i+m*3+0] += nax
                  shape.vertices[i+m*3+2] += naz
                  shape.vertices[i+m*3+1] = floor(shape.vertices[i+m*3+0],
                                              shape.vertices[i+m*3+2]) - 3
                }
              }
              //if(!((t*60|0)%240) || (t<.1)) Coordinates.SyncNormals(shape, true)
              renderer.Draw(shape)
            break
            default:
              shape.yaw += .01
              shape.pitch += .005
              renderer.Draw(shape)
            break
          }
        })
      }
      launch(960, 540)

      // db sync
      const URLbase = 'https://boss.mindhackers.org/flock'
      
      const syncPlayers = data => {
        players = data.map(player=>JSON.parse(player))
        iplayers.map(v=>v.keep = false)
        players.map(player => {
          var l = iplayers.filter(v=>(+v.id)==(+player.id))
          if(l.length){
            //l[0].name  = player.name
            //l[0].id    = player.id
            l[0].x     = player.x
            l[0].y     = player.y
            l[0].z     = player.z
            l[0].roll  = player.roll
            l[0].pitch = player.pitch
            l[0].yaw   = player.yaw
            l[0].keep  = true
          }else{
            var newObj = structuredClone(iplayerData)
            newObj.name  = newObj.name   = player.name
            newObj.id    = newObj.id     = +player.id
            newObj.x     = newObj.ix     = player.x
            newObj.y     = newObj.iy     = player.y
            newObj.z     = newObj.iz     = player.z
            newObj.roll  = newObj.iroll  = player.roll
            newObj.pitch = newObj.ipitch = player.pitch
            newObj.yaw   = newObj.iyaw   = player.yaw
            newObj.keep  = true
            iplayers = [...iplayers, newObj]
          }
          iplayers = iplayers.filter(v=>v.keep)
        })
        console.log(iplayers)
      }
      
      const launchLocalClient = data => {
        playerData = data
        playerData.id = +playerData.id
        setInterval(() => {
          coms('sync.php', 'syncPlayers')
        }, 1e3)
      }
    
      var playerData = {
        name: '', id: -1,
        x: 0, y: 0, z: 0,
        roll: 0, pitch: 0, yaw: 0,
      }
      
      // interpolated (smoothing/local) values
      var iplayerData = {
        name: '', id: -1,
        x: 0, y: 0, z: 0,
        roll: 0, pitch: 0, yaw: 0,
        ix: 0, iy: 0, iz: 0,
        iroll: 0, ipitch: 0, iyaw: 0,
        keep: false,
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
          //output.innerHTML = JSON.stringify(playerData)
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
      
      coms('launch.php', 'launchLocalClient')

    </script>
  </body>
</html>



FILE;
@unlink('../../flock/index.php');
file_put_contents('../../flock/index.html', $file);
?>