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
        ambientLight: .5,
        fov: 1500
      }
      var renderer = await Coordinates.Renderer(rendererOptions)
      
      renderer.z = 10
      
      Coordinates.AnimationLoop(renderer, 'Draw')

      var shaderOptions = [
        { uniform: {
          type: 'phong',
          value: .75
        } }
      ]
      var shader = await Coordinates.BasicShader(renderer, shaderOptions)


      var shapes = []
      var geoOptions = {
        shapeType: 'cube',
        size: 5,
        color: 0xffffff,
      }
      await Coordinates.LoadGeometry(renderer, geoOptions).then(async (geometry) => {
        shapes.push(geometry)
        await shader.ConnectGeometry(geometry)
      })  
      
      
      var splash = new Image('./splash.jpg')
      
      window.Draw = () => {
        shapes.forEach(shape => {
          shape.yaw += .01
          shape.pitch += .005
          renderer.Draw(shape)
        })
        
        var c = Coordinates.Overlay.c
        var ctx = Coordinates.Overlay.ctx
        
        ctx.fillStyle = '#f00'
        ctx.fillRect(100,100,100,100)
      }
      
    </script>
  </body>
</html>