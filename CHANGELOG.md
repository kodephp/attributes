# Changelog

本项目所有重要变更均记录于此文件。格式遵循 [Keep a Changelog](https://keepachangelog.com/)，
版本号遵循 [语义化版本 2.0.0](https://semver.org/lang/zh-CN/)。

## [2.0.0] - 2026-08-04

### 🔥 根因修复（重大）

- **修复属性注入整条链路静默失效**：1.x 的 `Attr` 门面只接受类名字符串或对象。
  一旦传入 `ReflectionClass` / `ReflectionProperty` / `ReflectionParameter`，由于它们本身也是“对象”，
  会被当作普通对象处理，转而读取 **Reflection 类自身** 的属性——永远返回空集合且不报错。
  2.0 新增 `TargetRef` 层，将所有目标统一归一化为 `Reflector` 实例并**原样透传**，
  从根因上杜绝该问题。目标不存在时抛出 `TargetNotFoundException`，不再静默返回空集合。

### ✨ 新增

- `TargetRef`：目标归一化与防碰撞缓存键生成（核心修复层）。
- `TargetSet`：位掩码目标集合，精确表达任意组合目标（如 类|方法），不再退化成 `All`。
- `Inspector`：围绕单一目标的链式属性检查器（`Attr::on($target)`）。
- 异常体系（`src/Exception/`）：`AttributeException`（标记接口）、`InvalidTargetException`、
  `TargetNotFoundException`、`AttributeInstantiationException`，取代 1.x 的静默失败。
- `Attr` 门面新增：`strict()`、`on()`、`instances()`、`ofClass/ofMethod/ofProperty/ofConstant/
  ofFunction/ofParameter/ofEnumCase()`、`methods/properties/constants/parameters()`。
- `Meta` 支持纯数据模式（`fromArray` / `toSnapshot` / `__serialize`），可被 Redis / 文件等
  持久化缓存安全存储（ReflectionAttribute 本身禁止序列化）。
- `Reader`：继承链读取支持父类 **private 属性**，并修复非继承模式误读父类私有属性的问题。
- `ArrayCache`：新增 LRU 淘汰（`$capacity`）、`deleteByPrefix()` 与 evictions 统计。
- `Scanner`：支持枚举声明、跳过匿名类与 `Foo::class` 常量表达式、返回跳过目标及原因。

### ⚡️ 改进

- 所有入口接受“宽目标”：任意 `Reflector`、闭包、`[类, 方法]` 可调用数组、
  以及 `Foo::method` / `Foo::$prop` / `Foo::CONST` / `Foo::method($arg)` / `strlen()` 字符串写法。
- `Attr::of/has/get/getAll` 新增 `$inherited` 参数，支持沿继承链读取。
- 缓存键防碰撞：不同类的同名方法/属性/参数不再互相覆盖；匿名类名 NUL 字节已转义。
- 严格模式：`Attr::strict(true)` 下属性实例化失败直接抛出，非严格模式静默跳过损坏项。

### 💥 破坏性变更（迁移指引）

- **最低 PHP 版本提升至 8.3+**（1.x 为 8.1+）。
- `Attr::of($class)` 不再接受第二个“是否继承”以外的隐藏行为；`$inherited` 现为显式参数。
- `Reader` 的部分方法签名变更（如 `getClassAttrs($class, $inherited)` 增加 `$inherited` 参数）。
- 1.x 中“传入 Reflection 对象却读到 Reflection 自身属性”的静默行为已被移除，
  若旧代码依赖该（错误）行为，请改为传入类名/对象或正确的 `Reflector` 实例。

## [1.2.3] - 历史版本

- 1.x 系列的基础属性读取能力（具体条目见历史提交记录）。
