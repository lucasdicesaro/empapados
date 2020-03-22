#!/bin/bash


echo "Borrando carpeta anterior"
rm -rf dist

echo "Creando carpeta dist, y copiando los archivos distribuibles"
mkdir dist

cp index.html dist
cp empapack.js dist
cp empadata.js dist

cd dist

echo "Se renombran los archivos"
timestamp=$(date +%Y%m%d%H%M%S)

sed -i -- "s/empadata.js/empadata_${timestamp}.js/g" index.html
sed -i -- "s/empapack.js/empapack_${timestamp}.js/g" index.html

mv empapack.js "empapack_${timestamp}.js"
mv empadata.js "empadata_${timestamp}.js"

cd ..

