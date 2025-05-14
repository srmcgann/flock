<?
$file = <<<'FILE'
<!DOCTYPE html>
<html>
  <head>
    <title>Coordinates boilerplate example</title>
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
      "./coordinates.js"
    
      var rendererOptions = {
        ambientLight: .4,
        fov: 1500
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 16
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
        shapeType: 'rectangle',
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
        await shader.ConnectGeometry(geometry)
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
          subs: 0,
          pitch: Math.PI,
          colorMix: 0,
          map: `level ${i+1}.jpg`,
        }
        await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
          await shader.ConnectGeometry(geometry)
          levelTiles = [...levelTiles, geometry]
        })
      }
      
      window.onkeydown = e => {
        if(e.keyCode == 37) recedeSel()
        if(e.keyCode == 39) advanceSel()
      }
      
      
      var curSel = 0
      const advanceSel = () => {
        if(++curSel > 4) curSel-=5
      }
      const recedeSel = () => {
        if(--curSel < 0) curSel+=5
      }
      
      var p, d, X, Y, Z
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
          tile.pitch = -renderer.pitch + Math.PI
          tile.x = S(p = Math.PI / levelTiles.length + 
                         Math.PI*2/levelTiles.length * idx +
                         Math.PI*2/5*curSel) * 15
          tile.y = 3
          tile.z = C(p) * 15
          tile.showBounding = tile.z < -12.5
          renderer.Draw(tile)
        })
        
        var c = Coordinates.Overlay.c
        var ctx = Coordinates.Overlay.ctx
        //ctx.drawImage(splash, 0,0,)
      }
      
    </script>
  </body>
</html>

FILE;
file_put_contents('../../flock/lobby.php', $file);
