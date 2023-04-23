#!/bin/bash

# 删除旧的代码
for file in ./*; do
  if [[ "$file" == *"router.proto" ]]; then
    continue;
  fi
  if [[ "$file" == *"gen.sh" ]]; then
      continue;
    fi
  rm -rf "$file";
done

#生成新的代码
# https://protobuf.dev/reference/php/php-generated/

# shellcheck disable=SC2046
root_dir="$(pwd)"
match="$root_dir/*.proto"
for file in $match; do
  proto="$(basename "$file")"
  echo "protoc --php_out=$root_dir --proto_path=$root_dir $proto"
  protoc --php_out="$root_dir" --proto_path="$root_dir" "$proto"
  # shellcheck disable=SC2181
  if [ $? != 0 ]; then
    # shellcheck disable=SC2162
    read
    exit $?
  fi
done

source_dir="$root_dir/NetsvrBusiness/Router/Proto"
cp -r -f "$source_dir" "$root_dir/../"
rm -fr "$root_dir/NetsvrBusiness"