<!DOCTYPE html>
<html>
  <head>
    <title>flock lobby</title>
    <style>
      body, html{
        background: #333;
        margin: 0;
        min-height: 100vh;
        overflow: hidden;
      }
    </style>
  </head>
  <body>
    <script type="module">
    
      import * as Coordinates from
      "../coordinates.js"
    
      var rendererOptions = {
        ambientLight: .33,
        fov: 1500 / 2,
        width: 960,
        height: 540,
        margin: 0,
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 27
      var splashImg = './splash.jpg'
      
      Coordinates.AnimationLoop(renderer, 'Draw')

      var shaderOptions = [
        { uniform: {
          type: 'phong',
          value: 0,
        } }
      ]
      var shader = await Coordinates.BasicShader(renderer, shaderOptions)


      var shapes = []
      var geoOptions = {
        shapeType: 'sprite',
        name: 'splash',
        scaleX: 16/9,
        size: 13.5,
        z: 130,
        y: -30,
        colorMix: 0,
        map: splashImg,
      }
      await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
        shapes.push(geometry)
        //await shader.ConnectGeometry(geometry)
      })
      
      
      var S = Math.sin
      var C = Math.cos
      var Rn = Math.random


      var levelTiles = []
      for(var i = 0; i<5; i++){
        var geoOptions = {
          shapeType: 'rectangle',
          scaleX: 512/358,
          involveCache: false,
          size: 2,
          subs: 3,
          pitch: Math.PI,
          colorMix: 0,
          //yaw: .001,
          roll: -.004,
          map: `level ${i+1}.png`,
        }
        await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          await shader.ConnectGeometry(geometry)
          levelTiles = [...levelTiles, geometry]
        })
      }
      
      window.onkeydown = e => {
        if(!gameLaunched){
          switch(e.keyCode){
            case 37: recedeSel(); break
            case 39: advanceSel(); break
            case 13: launch(curSel); break
          }
        }
      }
      
      
      var curSel = 0
      const advanceSel = () => {
        if(++curSel > 4) curSel-=5
      }
      const recedeSel = () => {
        if(--curSel < 0) curSel+=5
      }
      
      var gameLaunched = false
      const launch = level => {
        var l
        location.href = location.origin+(l=location.pathname.split('/'))
                          .filter((v,i)=>i<l.length-2).join('/') + `/?level=${level+1}`
        gameLaunched = true
      }
      
      var ip=Array(levelTiles.length).fill(0), cp=0, p=0, d, X, Y, Z
      var homing = 5, maxRot = .05
      window.Draw = () => {
        
        var t = renderer.t
        
        renderer.pitch = .25
        shapes.forEach(shape => {
          switch(shape.name){
            case 'splash':
              shape.pitch = -renderer.pitch + Math.PI
              shape.yaw   = -renderer.yaw
              shape.roll  = -renderer.roll
            break
          }
          renderer.Draw(shape)
        })
        
        levelTiles.map((tile, idx) => {
          cp = Math.PI / levelTiles.length + 
                         Math.PI*2/levelTiles.length * idx +
                         Math.PI*2/5*(2-curSel)
          while(Math.abs(cp - ip[idx]) > Math.PI){
            if(cp > ip[idx]){
              cp -= Math.PI*2
            }else{
              if(cp < ip[idx]) cp += Math.PI*2
            }
          }
          ip[idx] += Math.min(maxRot, Math.max(-maxRot, (cp - ip[idx]) / homing))
          
          tile.pitch = -renderer.pitch + Math.PI - .01
          tile.x = S(p = ip[idx]) * 15
          tile.y = 3.75
          tile.z = C(p) * 15
          if(tile.z < -14.5){
            var bounding = Coordinates.ShowBounding(tile, renderer, false)
            var pip = Coordinates.PointInPoly2D(renderer.mouseX,
                                          renderer.mouseY, bounding)
            if(pip){
              tile.boundingColor = 0x00ff88
              Coordinates.ShowBounding(tile, renderer, true)
            }
            if(!gameLaunched && renderer.mouseButton == 1 && pip) launch(curSel)
          }else if(tile.z < 10){
            var bounding = Coordinates.ShowBounding(tile, renderer, false)
            var pip = Coordinates.PointInPoly2D(renderer.mouseX,
                                          renderer.mouseY, bounding)
            if(pip){
              tile.boundingColor = 0xff0000
              Coordinates.ShowBounding(tile, renderer, true)
            }
            if(renderer.mouseButton == 1 && pip) curSel = idx
            
          }
          renderer.Draw(tile)
        })
        
        var c = Coordinates.Overlay.c
        var ctx = Coordinates.Overlay.ctx
        //ctx.drawImage(splash, 0,0,)
      }
      
    </script>
  </body>
</html>