# 堆栈跟踪格式化改进

## 概述

优化了 `TheHandler.php` 中的异常堆栈跟踪显示，使其在浏览器 Network 面板中更易读和调试。

## 改进内容

### 1. 格式化堆栈跟踪信息

将原来的字符串格式：
```
#0 /Users/dong/project/my/the-admin/the-admin-service/vendor/topthink/think-validate/src/Validate.php(731): think\Validate->checkItems('id', 'require|integer...', Array, 'id')
#1 /Users/dong/project/my/the-admin/the-admin-service/plugin/theadmin/app/validator/ValidatorBase.php(55): think\Validate->check(Array)
```

转换为结构化的 JSON 数组：
```json
[
    {
        "index": 0,
        "file": "/Users/dong/project/my/the-admin/the-admin-service/vendor/topthink/think-validate/src/Validate.php",
        "fileName": "Validate.php",
        "relativePath": "vendor/topthink/think-validate/src/Validate.php",
        "line": 731,
        "function": "think\\Validate->checkItems('id', 'require|integer...', Array, 'id')",
        "isProjectFile": true,
        "raw": "#0 /Users/dong/project/my/the-admin/the-admin-service/vendor/topthink/think-validate/src/Validate.php(731): think\\Validate->checkItems('id', 'require|integer...', Array, 'id')"
    }
]
```

### 2. 新增字段说明

- `index`: 堆栈跟踪的序号
- `file`: 完整的文件路径
- `fileName`: 文件名（不包含路径）
- `relativePath`: 相对于项目根目录的路径
- `line`: 行号
- `function`: 函数调用信息
- `isProjectFile`: 是否为项目文件（非第三方库）
- `raw`: 原始堆栈跟踪字符串

### 3. 调试优势

1. **结构化数据**: 便于前端解析和显示
2. **相对路径**: 更容易识别项目文件
3. **文件分类**: 区分项目文件和第三方库文件
4. **保留原始**: 同时保留原始格式供参考

## 使用方式

当发生异常时，在浏览器 Network 面板中查看响应，`traces` 字段将显示格式化的堆栈跟踪信息，便于调试和分析。

## 技术实现

- 使用正则表达式解析堆栈跟踪字符串
- 提取文件路径、行号、函数名等信息
- 计算相对路径和项目文件标识
- 保持向后兼容性
