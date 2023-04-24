#!/bin/bash

# shellcheck disable=SC2046
root_dir="$(pwd)"

# 删除旧代码
mkdir -p "$root_dir/../Protobuf/*"
rm -fr "$root_dir/../Protobuf/*"

# 生成新的代码
# https://protobuf.dev/reference/php/php-generated/
match="$root_dir/*.proto"
for file in $match; do
  proto="$(basename "$file")"
  echo "protoc --php_out=$root_dir/../Protobuf/ --proto_path=$root_dir $proto"
  protoc --php_out="$root_dir/../Protobuf/" --proto_path="$root_dir" "$proto"
  # shellcheck disable=SC2181
  if [ $? != 0 ]; then
    # shellcheck disable=SC2162
    read
    exit $?
  fi
done

# 移动位置
cp -r -f  ./../Protobuf/App/Protocol/Proto/Protobuf/* ./../Protobuf
rm -fr ./../Protobuf/App

oldStr="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message"
# 让所有的协议类都实现接口 \NetsvrBusiness\Contract\RouterDataInterface
routerDataInterface="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message implements \\\\NetsvrBusiness\\\\Contract\\\\RouterDataInterface"
# 路由类实现接口 \NetsvrBusiness\Contract\RouterInterface
routerInterface="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message implements \\\\NetsvrBusiness\\\\Contract\\\\RouterInterface"
# 让所有生成的协议类、路由类都引入 \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
# shellcheck disable=SC2034
routerAndDataForProtobufTrait="    use \\\\NetsvrBusiness\\\\Contract\\\\RouterAndDataForProtobufTrait;"
for file in ./../Protobuf/*.php; do
  sed -i "/^{$/a\\$routerAndDataForProtobufTrait" "$file"
  if [[ "$file" == *"Router.php" ]]; then
    sed -i "s/$oldStr/$routerInterface/g" "$file"
    continue;
  fi
  sed -i "s/$oldStr/$routerDataInterface/g" "$file"
done