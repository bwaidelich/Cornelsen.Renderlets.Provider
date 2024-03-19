# Wwwision.Renderlets.Provider

Neos package to provide snippets of data/rendered content (aka 'renderlets') to be consumed by 3rd parties

## Installation

Install via composer:

    composer require wwwision/renderlets-provider

## Usage

Renderlets can be defined underneath the Fusion path `/renderlets`:

```neosfusion
renderlets {
    some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
        renderer = afx`<p>This can be any component</p>`
    }
}
```

With this in place, the renderlet is exposed via HTTP on the endpoint `/__renderlet/some_renderlet`

### Parameters

Renderlets can define parameters that can be specified by the consumer via query arguments:

```neosfusion
renderlet_with_parameters = Wwwision.Renderlets.Provider:Renderlet {
    parameters {
        foo = true
        bar = false
    }
    renderer = afx`foo: {parameters.foo}, bar: {parameters.bar || 'default'}`
}
```

In this example, the "foo" parameter is required (`true`) while "bar" is optional.
If the renderlet endpoint is requested without any query parameters (`/__renderlet/renderlet_with_parameters`) a 400 HTTP response is returned with the body:

```html
Missing/empty parameter "foo"
```

If the parameters are specified (e.g. `/__renderlet/renderlet_with_parameters?foo=foo%20value&bar=bar%20value`) they are evaluated as expected:

```html
foo: foo value, bar: bar value
```

> [!NOTE]  
> Query parameters that don't match a configured parameter (e.g. `/__renderlet/renderlet_with_parameters?fo=typo`) also lead to a 400 status code to prevent misbehavior due to typos

### Caching

Each renderlet is assigned a `cacheId` that will be turned into an [ETag](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/ETag) HTTP header in the response.
This allows consumers to send a corresponding `If-None-Match` header in order to prevent unchanged renderlets from beeing transmitted again.

The `cacheId` is a random string by default that gets assigned at rendering time.
In order to keep that consistent, a corresponding `@cache` meta property is defined in the renderlet declaration (see https://docs.neos.io/guide/manual/rendering/caching).
If the renderlet content depends on other components or data, this property should be extended accordingly:

#### Example

```neosfusion
some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    @context {
        someNode = ${q(site).children('[instanceof Some.Package:SomeNodeType]').get(0)}
    }
    renderer = afx`Node label: {someNode.label}`
    @cache {
        entryTags {
            someNode = ${Neos.Caching.nodeTag(someNode)}
        }
    }
}
```

Alternatively, the `cacheId` can be set to a static (or dynamic) value to make it deterministic:

```neosfusion
some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    cacheId = 'some-static-value'
    renderer = afx`Static content`
}
```

For [Renderlet Props](#renderlet-props) the cache behavior can be configured via `renderer.@cache`

> [!NOTE]  
> Parameters are always part of the cache entryIdentifier, so that every parameter combination is cached individually

### HTTP Headers

#### Content-Type

By default, renderlets are rendered with a `Content-Type` header of "text/html".
This can be changed via the `httpHeaders` prop:

```neosfusion
some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    httpHeaders {
        'Content-Type' = 'text/plain'
    }
    renderer = 'This is some plain text'
}
```

#### CORS

By default, renderlets are rendered with a `Access-Control-Allow-Origin` header of "*" to allow them to be consumed without [CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS) restrictions.
This can be changed via the `httpHeaders` prop:

```neosfusion
some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    httpHeaders {
        'Access-Control-Allow-Origin' = 'some-domain.tld'
    }
    // ...
}
```

#### Other headers

Other HTTP headers can be added via the `httpHeaders` prop:

```neosfusion
some_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    httpHeaders {
        'Content-Language' = 'de-DE, en-CA'
        'X-Custom-Header' = 'some value'
    }
    // ...
}
```

### Localization

Renderlet enddpoints work independantly from the Neos routing. As a result, nodes from the content repository will be loaded in their default dimension.
But parameters are a good option to allow the consumer to change the language:

```neosfusion
localized_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    parameters {
        lang = true
    }
    @context {
        someNode = ${q(site).children('[instanceof Some.Package:SomeNodeType]').get(0)}
        someNode.@process.translate = ${q(value).context({dimensions: {language: [parameters.lang]}, targetDimensions: {language: parameters.lang}}).get(0)}
    }
    renderer = afx`{q(someNode).property('title')}`
}
```

In this case, a `lang` query argument has to be specified that is used to load a node in the respective context.

Alternatively, the parameter could be made optional:

```neosfusion
localized_renderlet = Wwwision.Renderlets.Provider:Renderlet {
    parameters {
        lang = false
    }
    @context {
        someNode = ${q(site).children('[instanceof Some.Package:SomeNodeType]').get(0)}
        someNode.@process.translate = ${q(value).context({dimensions: {language: [parameters.lang]}, targetDimensions: {language: parameters.lang}}).get(0)}
        someNode.@process.translate.@if.hasLanguageParameter = ${!String.isBlank(parameters.lang)}
    }
    renderer = afx`{q(someNode).property('title')}`
}
```

### Renderlet Props

The `RenderletProps` prototype can be used to render data structures rather than (HTML) content:

```neosfusion
some_renderlet_props = Wwwision.Renderlets.Provider:RenderletProps {
    properties {
        foo = 'bar'
        baz {
           foos = true 
        }
    }
}
```

This will render the following JSON on the endpoint `/__renderlet/some_renderlet_props`:

```json
{
	"foo": "bar",
	"baz": {
		"foos": true
	}
}
```

The `Content-Type` header of `RenderletProps` is `application/json` by default, but it can be changed as described above.

#### Cache segments

When rendering Fusion prototypes with their own `@cache` configuration within renderlets, this can lead to Content Cache markers to appear in the response (see [issue](https://github.com/bwaidelich/Wwwision.Renderlets.Provider/issues/3) for details).
Therefore, starting with version [1.3.0](https://github.com/bwaidelich/Wwwision.Renderlets.Provider/releases/tag/1.3.0) those markers are now stripped from the renderlet.

Note, that in order for the automatic cache flushing to work as expected, the `@cache` configuration has to be complete:

```neosfusion
some_renderlet_props = Wwwision.Renderlets.Provider:RenderletProps {
    @context {
        someNode = ${q(site).find('#517ad799-35df-4324-9429-5c75629a8b34').get(0)}
    }
    properties {
        someRenderedComponent = Some.Package:Foo {
            someNode = ${someNode}
        }
    }
    renderer.@cache {
        entryTags {
            someNode = ${Neos.Caching.nodeTag(someNode)}
        }
    }
}
```
